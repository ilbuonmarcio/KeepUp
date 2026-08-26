<?php

namespace App\Http\Controllers;

use App\Models\WindowsDomain;
use App\Services\WindowsDomainSynchronizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConfigurationController extends Controller
{
    public function index()
    {
        $domains = WindowsDomain::query()
            ->withCount('monitors')
            ->orderByRaw('LOWER(name) ASC')
            ->orderBy('name')
            ->get();

        return view('pages.configuration.index', compact('domains'));
    }

    public function store(Request $request, WindowsDomainSynchronizer $synchronizer)
    {
        $domain = new WindowsDomain;
        $this->fillDomain($domain, $request, $this->validateDomain($request));
        $domain->save();

        return $this->synchronizeAndRedirect($domain, $synchronizer, 'Windows domain added.');
    }

    public function update(Request $request, WindowsDomain $domain, WindowsDomainSynchronizer $synchronizer)
    {
        $oldKey = $domain->ssh_private_key;
        $this->fillDomain($domain, $request, $this->validateDomain($request, $domain));
        $domain->save();

        $response = $this->synchronizeAndRedirect($domain, $synchronizer, 'Windows domain updated.');
        WindowsDomain::deleteSshKeyIfUnused($oldKey);

        return $response;
    }

    public function destroy(WindowsDomain $domain)
    {
        $name = $domain->name;
        $domain->delete();

        return redirect()->route('configuration.index')
            ->with('status', "{$name} and its discovered monitors were removed.");
    }

    public function sync(WindowsDomain $domain, WindowsDomainSynchronizer $synchronizer)
    {
        return $this->synchronizeAndRedirect($domain, $synchronizer, 'Windows domain synchronized.');
    }

    private function validateDomain(Request $request, ?WindowsDomain $domain = null): array
    {
        $request->merge([
            'domain_name' => strtolower(trim((string) $request->input('domain_name'), " \t\n\r\0\x0B.")),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('windows_domains', 'domain_name')->ignore($domain),
            ],
            'ldap_host' => ['required', 'string', 'max:255'],
            'ldap_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'ldap_security' => ['required', Rule::in(['ldap', 'starttls', 'ldaps'])],
            'base_dn' => ['nullable', 'string', 'max:1000'],
            'discovery_username' => ['required', 'string', 'max:255'],
            'discovery_password' => ['nullable', 'string', 'max:1000'],
            'monitor_username' => ['required', 'string', 'max:255'],
            'auth_method' => ['required', Rule::in(['password', 'ssh_private_key'])],
            'monitor_password' => ['nullable', 'string', 'max:1000'],
            'ssh_private_key' => ['nullable', 'file', 'max:64'],
            'threshold_uptime' => ['required', 'numeric', 'min:0'],
            'threshold_updates_available' => ['required', 'integer', 'min:1'],
        ]);
    }

    private function fillDomain(WindowsDomain $domain, Request $request, array $validated): void
    {
        $previousAuthMethod = $domain->auth_method;
        $domainName = strtolower(trim($validated['domain_name'], " \t\n\r\0\x0B."));

        $domain->name = $validated['name'];
        $domain->domain_name = $domainName;
        $domain->ldap_host = strtolower(trim($validated['ldap_host']));
        $domain->ldap_port = $validated['ldap_port'];
        $domain->ldap_security = $validated['ldap_security'];
        $domain->base_dn = $this->normalizeBaseDn($validated['base_dn'] ?? null, $domainName);
        $domain->discovery_username = trim($validated['discovery_username']);
        $domain->monitor_username = trim($validated['monitor_username']);
        $domain->auth_method = $validated['auth_method'];
        $domain->threshold_uptime = $validated['threshold_uptime'];
        $domain->threshold_updates_available = $validated['threshold_updates_available'];

        if (filled($validated['discovery_password'] ?? null)) {
            $domain->discovery_password = $validated['discovery_password'];
        } elseif (! $domain->exists) {
            throw ValidationException::withMessages([
                'discovery_password' => 'The LDAP discovery password is required.',
            ]);
        }

        if ($validated['auth_method'] === 'password') {
            if (filled($validated['monitor_password'] ?? null)) {
                $domain->monitor_password = $validated['monitor_password'];
            } elseif (! $domain->exists || $previousAuthMethod !== 'password' || blank($domain->monitor_password)) {
                throw ValidationException::withMessages([
                    'monitor_password' => 'The monitor password is required.',
                ]);
            }

            $domain->ssh_private_key = null;

            return;
        }

        if ($request->hasFile('ssh_private_key')) {
            $domain->ssh_private_key = $this->storePrivateKey($request);
        } elseif (! $domain->exists
            || $previousAuthMethod !== 'ssh_private_key'
            || blank($domain->ssh_private_key)
            || ! Storage::disk('private_keys')->exists($domain->ssh_private_key)) {
            throw ValidationException::withMessages([
                'ssh_private_key' => 'An SSH private key is required.',
            ]);
        }

        $domain->monitor_password = null;
    }

    private function storePrivateKey(Request $request): string
    {
        $privateKey = $request->file('ssh_private_key')->getContent();

        if (! preg_match('/-----BEGIN (?:OPENSSH |RSA |EC |DSA )?PRIVATE KEY-----/', $privateKey)) {
            throw ValidationException::withMessages([
                'ssh_private_key' => 'The uploaded file is not a supported SSH private key.',
            ]);
        }

        $directory = Storage::disk('private_keys')->path('');
        File::ensureDirectoryExists($directory, 0700);
        chmod($directory, 0700);

        $filename = Str::random(40).'.key';
        Storage::disk('private_keys')->put($filename, Crypt::encryptString($privateKey));
        chmod(Storage::disk('private_keys')->path($filename), 0600);

        return $filename;
    }

    private function normalizeBaseDn(?string $baseDn, string $domainName): string
    {
        $baseDn = trim((string) $baseDn);

        if ($baseDn === '') {
            $baseDn = $domainName;
        }

        if (! str_contains($baseDn, '=')) {
            return collect(explode('.', strtolower(trim($baseDn, '.'))))
                ->filter()
                ->map(fn (string $part) => 'DC='.$part)
                ->implode(',');
        }

        return $baseDn;
    }

    private function synchronizeAndRedirect(
        WindowsDomain $domain,
        WindowsDomainSynchronizer $synchronizer,
        string $successMessage,
    ) {
        try {
            $count = $synchronizer->sync($domain);

            return redirect()->route('configuration.index')
                ->with('status', "{$successMessage} {$count} computers discovered.");
        } catch (Throwable $exception) {
            Log::warning("Windows domain discovery failed for [{$domain->name}]", [
                'exception' => $exception->getMessage(),
            ]);

            return redirect()->route('configuration.index')
                ->with('error', "The configuration was saved, but discovery failed: {$exception->getMessage()}");
        }
    }
}

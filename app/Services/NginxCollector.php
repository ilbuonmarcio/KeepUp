<?php

namespace App\Services;

use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class NginxCollector
{
    /** @return array{nginx_running: ?bool, nginx_version: ?string} */
    public function collect(Ssh $ssh): array
    {
        try {
            $request = $ssh->execute(<<<'SH'
                [ "$(uname -s)" = Linux ] || exit 1
                pids=$(pgrep -x nginx) || {
                    case $? in
                        1) printf '%s\n' stopped; exit 0 ;;
                        *) exit 1 ;;
                    esac
                }

                running=false
                for pid in $pids; do
                    # Nested PID namespaces belong to containers, outside this host check.
                    if awk '$1 == "NSpid:" && NF > 2 { nested = 1 } END { exit !nested }' "/proc/$pid/status" 2>/dev/null; then
                        continue
                    fi
                    [ -r "/proc/$pid/status" ] || continue
                    if [ "$running" = false ]; then
                        printf '%s\n' running
                        running=true
                    fi

                    executable=$(readlink "/proc/$pid/exe") || continue
                    case "$executable" in
                        *' (deleted)')
                            # An upgrade leaves a replacement at the same path; an uninstall may leave only the process.
                            installed_path=${executable%' (deleted)'}
                            [ -x "/proc/$pid/root$installed_path" ] || continue
                            ;;
                    esac

                    if version=$("/proc/$pid/exe" -v 2>&1); then
                        printf '%s\n' "$version"
                        break
                    fi
                done
                if [ "$running" = false ]; then
                    printf '%s\n' stopped
                fi
                SH);
        } catch (ProcessTimedOutException) {
            return ['nginx_running' => null, 'nginx_version' => null];
        }

        $running = null;
        $version = null;

        if ($request->isSuccessful()) {
            [$status, $output] = array_pad(explode("\n", trim($request->getOutput()), 2), 2, '');
            $running = match ($status) {
                'running' => true,
                'stopped' => false,
                default => null,
            };

            if ($running && preg_match('/^nginx version: nginx\/([0-9]+(?:\.[0-9]+)+)\b/m', $output, $matches)) {
                $version = $matches[1];
            }
        }

        return ['nginx_running' => $running, 'nginx_version' => $version];
    }
}

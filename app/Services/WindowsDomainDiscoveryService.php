<?php

namespace App\Services;

use App\Models\WindowsDomain;
use RuntimeException;

class WindowsDomainDiscoveryService
{
    private const PAGED_RESULTS_OID = '1.2.840.113556.1.4.319';

    public function discover(WindowsDomain $domain): array
    {
        if (! extension_loaded('ldap')) {
            throw new RuntimeException('The PHP LDAP extension is not installed.');
        }

        $scheme = $domain->ldap_security === 'ldaps' ? 'ldaps' : 'ldap';
        $connection = @ldap_connect("{$scheme}://{$domain->ldap_host}:{$domain->ldap_port}");

        if ($connection === false) {
            throw new RuntimeException('Unable to initialize the LDAP connection.');
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 15);

        if ($domain->ldap_security === 'starttls' && ! @ldap_start_tls($connection)) {
            throw new RuntimeException('Unable to start LDAP TLS: '.ldap_error($connection));
        }

        if (! @ldap_bind($connection, $domain->discovery_username, $domain->discovery_password)) {
            throw new RuntimeException('LDAP bind failed: '.ldap_error($connection));
        }

        $filter = '(&(objectCategory=computer)(dNSHostName=*)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';
        $attributes = ['name', 'dnshostname', 'operatingsystem'];
        $computers = [];
        $cookie = '';

        do {
            $controls = [[
                'oid' => self::PAGED_RESULTS_OID,
                'iscritical' => true,
                'value' => ['size' => 1000, 'cookie' => $cookie],
            ]];
            $search = @ldap_search(
                $connection,
                $domain->base_dn,
                $filter,
                $attributes,
                timelimit: 30,
                controls: $controls,
            );

            if ($search === false) {
                throw new RuntimeException('LDAP computer search failed: '.ldap_error($connection));
            }

            $entries = ldap_get_entries($connection, $search);
            for ($index = 0; $index < ($entries['count'] ?? 0); $index++) {
                $hostname = strtolower(trim($entries[$index]['dnshostname'][0] ?? ''));

                if ($hostname === '') {
                    continue;
                }

                $computers[$hostname] = [
                    'hostname' => $hostname,
                    'name' => trim($entries[$index]['name'][0] ?? strtok($hostname, '.')),
                    'operating_system' => trim($entries[$index]['operatingsystem'][0] ?? ''),
                ];
            }

            $responseControls = [];
            ldap_parse_result($connection, $search, $errorCode, $matchedDn, $errorMessage, $referrals, $responseControls);
            $cookie = $responseControls[self::PAGED_RESULTS_OID]['value']['cookie'] ?? '';
        } while ($cookie !== '');

        ldap_unbind($connection);

        return array_values($computers);
    }
}

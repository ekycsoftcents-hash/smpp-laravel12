<?php

namespace App\Services\Jasmin;

use RuntimeException;

class JasminSmppProvisioningService
{
    public function provision(string $uid, string $systemId, string $password, int $maxBind = 1): void
    {
        $command = sprintf(
            'python3 /var/www/html/scripts/provision_smpp_user.py --uid %s --username %s --password %s --max-bind %d',
            escapeshellarg($uid), escapeshellarg($systemId), escapeshellarg($password), $maxBind
        );
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start Jasmin provisioning bridge.');
        $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0 || !str_contains($stdout, 'PROVISIONED')) {
            throw new RuntimeException(trim($stderr ?: $stdout ?: 'Jasmin SMPP user provisioning failed.'));
        }
    }
}

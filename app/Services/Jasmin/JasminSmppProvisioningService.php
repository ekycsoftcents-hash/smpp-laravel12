<?php

namespace App\Services\Jasmin;

use RuntimeException;

class JasminSmppProvisioningService
{
    public function provision(string $uid, string $systemId, string $password, int $maxBind = 1): void
    {
        $this->run('provision', $uid, [
            '--username' => $systemId,
            '--password' => $password,
            '--max-bind' => (string) $maxBind,
        ], 'PROVISIONED');
    }

    public function disable(string $uid): void
    {
        $this->run('disable', $uid, [], 'DISABLED');
    }

    public function enable(string $uid): void
    {
        $this->run('enable', $uid, [], 'ENABLED');
    }

    private function run(string $action, string $uid, array $arguments, string $expectedOutput): void
    {
        $parts = ['python3', '/var/www/html/scripts/provision_smpp_user.py', $action, '--uid', $uid];
        foreach ($arguments as $key => $value) {
            $parts[] = $key;
            $parts[] = $value;
        }
        $command = implode(' ', array_map('escapeshellarg', $parts));
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start Jasmin provisioning bridge.');
        $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0 || !str_contains($stdout, $expectedOutput)) {
            throw new RuntimeException(trim($stderr ?: $stdout ?: 'Jasmin SMPP operation failed.'));
        }
    }
}

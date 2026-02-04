<?php
namespace NGN\Lib\Auth;

use NGN\Lib\Config;

class DevUserService
{
    private string $ledgerPath;

    public function __construct(Config $config)
    {
        $this->ledgerPath = $config->usersLedgerPath();
    }

    /**
    * Validate credentials against a dev users ledger JSON file.
    * Ledger format: [{"email":"user@example.com","password":"plain-or-bcrypt"}]
    */
    public function validate(string $email, string $password): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') return false;
        $users = $this->loadUsers();
        foreach ($users as $u) {
            $e = strtolower((string)($u['email'] ?? ''));
            if ($e === $email) {
                $stored = (string)($u['password'] ?? '');
                if ($stored === '') return false;
                // Support bcrypt hashes or plain text for dev convenience
                if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
                    return password_verify($password, $stored);
                }
                return hash_equals($stored, $password);
            }
        }
        return false;
    }

    /**
     * @return array<int, array{email:string,password:string,role?:string,name?:string}>
     */
    public function loadUsers(): array
    {
        if (!is_file($this->ledgerPath)) return [];
        $json = file_get_contents($this->ledgerPath);
        $arr = json_decode($json, true);
        return is_array($arr) ? $arr : [];
    }
}

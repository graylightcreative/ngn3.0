<?php
namespace NGN\Lib\Auth;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class LegacyUserService
{
    private Config $config;
    private ?PDO $pdo = null;

    public function __construct(Config $config)
    {
        $this->config = $config;
        try {
            $this->pdo = ConnectionFactory::read($config);
        } catch (\Throwable $e) {
            $this->pdo = null; // DB may be unavailable in some dev setups
        }
    }

    /**
     * Authenticate against legacy users table using password_verify()-compatible hashes.
     * Returns [bool $ok, array|null $user, string|null $err]
     * $user shape: { id:int, email:string, role_id:int|null, is_admin:bool }
     */
    public function authenticate(string $email, string $password): array
    {
        $emailNorm = trim(strtolower($email));
        if ($emailNorm === '' || $password === '' || !$this->pdo) {
            return [false, null, 'unavailable'];
        }

        // Resolve identifiers from configuration (safely quote)
        $table = $this->safeIdent($this->config->legacyUsersTable());
        $colEmail = $this->safeIdent($this->config->legacyEmailColumn());
        $colPass  = $this->safeIdent($this->config->legacyPasswordColumn());
        $colRole  = $this->safeIdent($this->config->legacyRoleIdColumn());

        $sql = "SELECT `Id` AS id, `{$colEmail}` AS email, `{$colPass}` AS password_hash, `{$colRole}` AS role_id
                FROM `{$table}`
                WHERE LOWER(`{$colEmail}`) = :email
                LIMIT 1";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':email', $emailNorm, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return [false, null, 'invalid'];
            }
            $hash = (string)($row['password_hash'] ?? '');
            if ($hash === '' || !password_verify($password, $hash)) {
                return [false, null, 'invalid'];
            }
            $roleId = isset($row['role_id']) ? (int)$row['role_id'] : null;
            $isAdmin = $this->isAdminRole($roleId);
            $user = [
                'id' => (int)($row['id'] ?? 0),
                'email' => (string)($row['email'] ?? $email),
                'role_id' => $roleId,
                'is_admin' => $isAdmin,
            ];
            return [true, $user, null];
        } catch (\Throwable $e) {
            // Do not leak details upstream
            return [false, null, 'error'];
        }
    }

    private function isAdminRole(?int $roleId): bool
    {
        $ids = $this->config->legacyAdminRoleIds();
        if (empty($ids)) return false;
        return in_array((string)($roleId ?? ''), $ids, true) || in_array((int)($roleId ?? 0), array_map('intval', $ids), true);
    }

    private function safeIdent(string $name): string
    {
        // Allow only letters, numbers, and underscore; strip others
        $clean = preg_replace('/[^A-Za-z0-9_]/', '', $name);
        if ($clean === '' ) {
            // Fallback to safe default if somehow emptied
            return 'users';
        }
        return $clean;
    }
}

<?php
namespace NGN\Lib\Auth;

class Rbac
{
    /**
     * Simple capability checker.
     * roles: admin, editor, moderator, viewer, ops
     * capabilities: posts.read, posts.write, settings.read, settings.write, keys.read, keys.write, db.admin
     */
    public static function can(array $claims, string $capability): bool
    {
        $role = strtolower((string)($claims['role'] ?? ''));
        if ($role === 'admin') return true; // admin can do everything (dev-only for now)
        $map = [
            'editor' => ['posts.read','posts.write','settings.read','keys.read'],
            'moderator' => ['posts.read','settings.read','keys.read'],
            'viewer' => ['posts.read'],
            'ops' => ['settings.read','keys.read','keys.write','db.admin'],
        ];
        $caps = $map[$role] ?? [];
        return in_array($capability, $caps, true);
    }
}

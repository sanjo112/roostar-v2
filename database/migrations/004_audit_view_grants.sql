INSERT IGNORE INTO permission_grants (id, user_id, permission, scope_type, scope_id, created_at)
SELECT UUID(), user_id, 'audit.view', scope_type, scope_id, NOW()
FROM permission_grants
WHERE permission = 'users.manage';

<?php
/**
 * Super-admin guard — include after api_common.php.
 * Terminates with 403 if the authenticated user is not a super admin.
 */
if (empty($currentUser['is_super_admin'])) {
    ApiResponse::error('Super admin access required', 403);
}

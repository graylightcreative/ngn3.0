<?php
/**
 * Token storage script - include this in admin pages after $mintedToken is set
 * Outputs JavaScript to store the minted token in localStorage
 */
?>
<!-- DEBUG: Session status: <?= session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT_ACTIVE' ?> -->
<!-- DEBUG: Session User: <?= isset($_SESSION['User']) ? 'SET (RoleId=' . ($_SESSION['User']['RoleId'] ?? 'none') . ')' : 'NOT SET' ?> -->
<!-- DEBUG: LoggedIn: <?= isset($_SESSION['LoggedIn']) ? $_SESSION['LoggedIn'] : 'NOT SET' ?> -->
<!-- DEBUG: mintedToken: <?= !empty($mintedToken) ? 'SET (' . strlen($mintedToken) . ' chars)' : 'EMPTY' ?> -->
<?php if (!empty($mintedToken)): ?>
<script>
(function(){
  try {
    localStorage.setItem('ngn_admin_token', <?= json_encode($mintedToken) ?>);
    localStorage.setItem('admin_token', <?= json_encode($mintedToken) ?>);
    var cookie = 'NGN_ADMIN_BEARER=' + encodeURIComponent(<?= json_encode($mintedToken) ?>) + '; Path=/; SameSite=Lax';
    if (location.protocol === 'https:') cookie += '; Secure';
    document.cookie = cookie;
    console.log('[NGN] Admin token stored successfully');
  } catch (e) {
    console.error('[NGN] Token storage failed:', e);
  }
})();
</script>
<?php endif; ?>


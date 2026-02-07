<?php
// Global footer with email capture popup
// Include this file at the end of every page before </body>

// Ensure session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// Check if user is authenticated
$authenticated = isset($_SESSION['User']) && !empty($_SESSION['User']);
?>

<!-- Email Capture Popup (Tailwind) -->
<?php if (!$authenticated): ?>
<div class="popup fixed inset-0 flex items-center justify-center bg-black/70 z-50" id="contactJoinPopup" style="display:none;">
  <div class="bg-zinc-900 rounded-lg p-8 max-w-md w-full mx-4 relative border border-zinc-700">
    <button class="close-popup absolute top-4 right-4 text-zinc-400 hover:text-white text-2xl leading-none" aria-label="Close">
      <i class="bi bi-x"></i>
    </button>

    <div class="mb-6">
      <h2 class="text-2xl font-black text-white mb-4">🤘 Join the NGN Movement!</h2>
      <p class="text-zinc-300 text-sm leading-relaxed">
        Don't miss a beat of the underground music scene. Sign up for the NextGen Noise newsletter and get exclusive chart insights, curated music picks, behind-the-scenes stories, and launch updates!
      </p>
    </div>

    <form action='' method='post' novalidate class='newsletterSignup space-y-4'>
      <div class='grid grid-cols-2 gap-4'>
        <div>
          <input type='text' class='newsletterFirstName w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-white placeholder-zinc-500 focus:border-zinc-500 focus:outline-none' placeholder='First name' value='' required>
        </div>
        <div>
          <input type='email' class='newsletterEmail w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-white placeholder-zinc-500 focus:border-zinc-500 focus:outline-none' placeholder='Email' value='' required>
        </div>
      </div>

      <button type='submit' class='w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition-colors mt-6'>Get Exclusive Access</button>
      <button type='button' class='w-full bg-transparent text-zinc-400 hover:text-white font-medium py-2 rounded-lg transition-colors dismiss-popup'>Not now</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Auth state for email popup -->
<script>
  window.NGN = window.NGN || {};
  window.NGN.isAuthenticated = <?php echo $authenticated ? 'true' : 'false'; ?>;
</script>

<!-- jQuery (required for popup and form handling) -->
<script src='https://code.jquery.com/jquery-3.7.1.min.js'></script>

<!-- Axios (required for form submission) -->
<script src='https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js'></script>

<!-- Newsletter signup script with email capture -->
<script src="/lib/js/newsletter-signup.js?v=<?=time();?>"></script>

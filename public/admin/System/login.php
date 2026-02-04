<?php
// Admin-safe login page (allowed during maintenance via /admin/* allowlist)
// Minimal, header-free login to bypass any legacy maintenance redirects in header.php
$root = '../';
require $root.'lib/definitions/site-settings.php';

$return = isset($_GET['r']) ? urldecode($_GET['r']) : '';
$error  = isset($_GET['e']) ? urldecode($_GET['e']) : false;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-dark text-white">

<main class="py-5">
  <div class="container">
    <div class="row">
      <div class="col-md-6 mx-auto">
        <div class="card bg-black border-secondary">
          <h1 class="card-header h4">Admin Login</h1>
          <div class="card-body">
            <?php if($error):?>
              <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif;?>
            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" id="email" class="form-control" />
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" id="password" class="form-control" />
            </div>
            <input type="hidden" id="return" value="<?php echo htmlspecialchars($return); ?>" />
            <button class="btn btn-primary w-100" id="siteLogin">Login</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
    document.getElementById('siteLogin').addEventListener('click', function(e){
        e.preventDefault();
        const email = document.querySelector('input#email');
        const password = document.querySelector('input#password');
        const redirect = document.querySelector('input#return');
        if(email.value.trim()!=='' && password.value.trim()!==''){
            axios.post('<?php echo $root; ?>lib/handlers/login.php', {
                email: email.value,
                password: password.value,
                redirect: redirect.value
            })
            .then(function(res){
                if(res.data){
                    if(res.data.success){
                        localStorage.setItem('mailingSubscribed', 'true');
                        window.location.href = res.data.redirect;
                    } else {
                        alert(res.data.message);
                    }
                } else {
                    alert('An unknown error has occurred');
                    console.error(res.data);
                }
            });
        }
    });
</script>
</body>
</html>

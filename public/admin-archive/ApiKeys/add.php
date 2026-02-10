
<?php
    $root = $_SERVER['DOCUMENT_ROOT'].'/';
    $adminRoot = $root.'admin/';
    require $root.'lib/definitions/site-settings.php';
    require $adminRoot.'lib/definitions/admin-settings.php';
    require $root.'lib/partials/head.php';

    $users = browse('users');
    $userOptions = [];
    foreach($users as $user){
        $roles = [1];
        if(in_array($user['RoleId'], $roles)){
            $userOptions[$user['Id']] = $user['Title'];
        }
    }

    function generateKey(){
        $key = '';
        for($i=0;$i<10;$i++){
            $key .= chr(rand(97,122));
        }
        return $key;
    }
?>
</head>
<body>
<?php require '../lib/partials/header.php';?>
<main class="py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-10 mx-auto">
                <h1>Add Post</h1>
                <form action="lib/handlers/add.php" method="post" id="addPostForm" enctype="multipart/form-data">
	                <?=createFloatingFormGroup('Key <span class="text-danger">*</span>','text','key',generateKey(),'',true);?>
	                <?=createFloatingFormGroup('User <span class="text-danger">*</span>','select','user_id','',$userOptions,true);?>

                    <button type="submit" class="btn btn-lg btn-primary d-block w-100">Add Key</button>
                </form>
            </div>
        </div>

    </div>
</main>

<?php require '../lib/partials/footer.php';?>
<script src="<?=$GLOBALS['Default']['Baseurl'];?>admin/lib/js/admin.js"></script>
</body>
</html>
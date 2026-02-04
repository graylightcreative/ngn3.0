<header class='bg-black p-2'>
    <div class='container-fluid'>
        <div class='row align-items-center'>
            <div class='col-10 col-lg-3 col-xl-2'>
                <a href="<?= $GLOBALS['Default']['Baseurl']; ?>"><img
                            src="<?= $GLOBALS['Default']['Baseurl']; ?>lib/images/site/web-light-1.png"
                            alt="<?= $GLOBALS['Default']['Author']; ?>" class='img-fluid w-100'></a>
            </div>
            <div class='col-lg-6 col-xl-8 d-none d-lg-inline-block'>

            </div>
            <div class='col-2 col-lg-3 col-xl-2 align-items-center'>
                <button class='btn btn-primary no-loading' type='button' data-bs-toggle='offcanvas'
                        data-bs-target='#adminMenu' aria-controls='adminMenu'>
                    <i class='bi bi-list fs-4'></i>
                </button>

            </div>
        </div>
    </div>
    <div class='offcanvas offcanvas-start' tabindex='-1' id='adminMenu' aria-labelledby='adminMenuLabel'>
        <div class='offcanvas-header'>
            <h5 class='offcanvas-title' id='adminMenuLabel'>Main Menu</h5>
            <button type='button' class='btn-close' data-bs-dismiss='offcanvas' aria-label='Close'></button>
        </div>
        <div class='offcanvas-body'>
            <ul class='nav flex-column'>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>admin/claims" class='nav-link'>Claims</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>admin/contacts" class='nav-link'>Contacts</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>admin/posts" class='nav-link'>Posts</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>admin/users" class='nav-link'>Users</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>admin/ads" class='nav-link'>Ads</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>admin/videos" class='nav-link'>Videos</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>admin/albums" class='nav-link'>Albums</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>admin/songs" class='nav-link'>Songs</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>logout" class='nav-link'>Logout</a></li>

            </ul>
        </div>
    </div>
</header>
<?php

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

// set counts in session if not set
$labelCount = 0;
$artistCount = 0;
$postCount = 0;
$stationCount = 0;

$config = new Config();
$pdo = ConnectionFactory::write($config);
$spins_pdo = ConnectionFactory::named($config, 'SPINS2025');
if(!isset($_SESSION['labelCount'])){
    $ls = readMany('users','RoleId',7);
    if($ls){
        $labelCount = count($ls);
    }
    $_SESSION['labelCount'] = $labelCount;
}
if(!isset($_SESSION['artistCount'])){
   $a = readMany('users','RoleId',3);
   if($a){
       $artistCount = count($a);
   }


   $_SESSION['artistCount'] = $artistCount;
}
if(!isset($_SESSION['postCount'])){
    $ps = readMany('posts','Published',1);
    if($ps){
        $postCount = count($ps);
    }
    $_SESSION['postCount'] = $postCount;
}
if(!isset($_SESSION['radioCount'])){
    $stations = readMany('users','RoleId',9);
    if($stations){
        $stationCount = count($stations);
    }
    $_SESSION['stationCount'] = $stationCount;

}

if(!isset($_SESSION['venuesCount'])){

    $venues = readMany('users','RoleId',11);
    if($venues){
        $venuesCount = count($venues);
    }
    $_SESSION['venuesCount'] = $venuesCount;
}

?>

<input type='hidden' id='baseurl' value="<?= $GLOBALS['Default']['Baseurl']; ?>">
<?php if(!$GLOBALS['theme']['light']):?>
    <input type='hidden' id='theme' value="dark">
<?php else:?>
    <input type='hidden' id='theme' value="light">
<?php endif;?>
<input type="hidden" id="themeColor" value="<?=$GLOBALS['theme']['color'];?>">

<header class='bg-black'>
    <?php if($authenticated):?>

        <div class="alert text-bg-secondary text-center small mb-1 alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle-fill"></i> 02/24/25: Waiting on Facebook Application Approval For
            Full Instagram / Facebook Insight Integration
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div></div>

    <div class="bg-primary py-2">
       <div class="container">
           <div class="row ">
               <div class="col">
                   <?php if($authenticated):?>
                       <?php
                       // get users role

                       $loggedInUser = read('users','Id',$_SESSION['User']['Id']);
                       $r = read('UserRoles','Id',$_SESSION['User']['RoleId']);
                       $u = $GLOBALS['Default']['Baseurl'];
                       switch($r['Title']){
                           case "Admin":
                               $u.= "admin/";
                               break;
                           case "Radio Station":
                               $u.= "stations/{$loggedInUser['Slug']}";
                               break;
                           case "Label":
                               $u.= "labels/{$loggedInUser['Slug']}";
                               break;
                           case "Artist":
                               $u.= "artists/{$loggedInUser['Slug']}";
                               break;
                           case "Writer":
                               $u.= "writers/{$loggedInUser['Slug']}";
                               break;
                           case "Moderator":
                               $u.= "moderators/{$loggedInUser['Slug']}";
                               break;
                           case "Advertiser":
                               $u.= "advertisers/{$loggedInUser['Slug']}";
                               break;

                       }
                       ?>
                       <?php if($loggedInUser['RoleId']===9):?>
                           <div class="container-fluid">
                               <div class="row">
                                   <div class="col">
                                       <?php
                                       // Determine if this station needs to update spins
                                       $ssql = 'SELECT * FROM SpinData WHERE `StationId` = '.$loggedInUser['Id'].' ORDER BY `Timestamp` DESC LIMIT 1';
                                       $squery = queryByDB($spins_pdo, $ssql, []);
                                       if($squery && !empty($squery)){
                                           $stationSpins = $squery[0];
                                           if (strtotime($stationSpins['Timestamp']) < time() - (7 * 24 * 60 * 60)) { // Check if data is over a week old
                                               echo '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Spins Outdated! <a href="https://nextgennoise.com/stations/edit/spins/" class="btn btn-sm btn-danger" title="Update spins now">Update Now</a></div>';
                                           }
                                       } else {
                                           echo '<div class="alert alert-warning">The station "' . htmlspecialchars($loggedInUser['Title']) . '" has not added any spins yet. <a href="https://nextgennoise.com/stations/edit/spins/" class="btn btn-sm btn-warning" title="Add your first spins now">Add Spins Now</a></div>';
                                       }
                                       ?>
                                   </div>
                               </div>
                           </div>
                       <?php endif;?>
                       <div class="">
                           <div class="row align-items-center">
                               <div class="col">
                                   <a href="<?=$u;?>" class="btn btn-dark py-2 no-loading"><?php

                                       ?>
                                       <?php if (empty($loggedInUser['Image'])): ?>
                                           <img src="<?= $GLOBALS['Default']['Baseurl']; ?>lib/images/labels/default.jpg" alt="Placeholder Image" class="rounded-circle" width="34px">
                                       <?php else: ?>
                                           <img src="<?= $GLOBALS['Default']['Baseurl']; ?>lib/images/users/<?=$loggedInUser['Slug'];?>/<?= $loggedInUser['Image']; ?>" alt="Placeholder Image" class="rounded-circle" width="30px">
                                       <?php endif; ?>
                                       <span class="text-light mx-2 fs-5"><?=$loggedInUser['Title'];?></span>
                                   </a>
                               </div>
                               <div class="col">
                                   <?php
                                        if($loggedInUser['RoleId']===9){
                                            $userRadioSpins = readByDB($spins_pdo, 'SpinData', 'StationId', $loggedInUser['Id']);
                                            $s = 'SELECT * FROM SpinData WHERE StationId = '.$loggedInUser['Id'].' AND Timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY Timestamp DESC LIMIT 1';
                                            $lastSpinDate = queryByDB($spins_pdo, $s, []);


                                            if (!empty($lastSpinDate)) {
                                                $dateAdded = new DateTime($lastSpinDate[0]['Timestamp']);
                                                $now = new DateTime();

                                                $interval = $dateAdded->diff($now);

                                                if ($interval->days >= 7) {
                                                    echo '<div class="alert text-bg-danger text-center m-0">Time to upload your spins <a href="https://nextgennoise.com/stations/edit/spins/" class="btn btn-sm btn-outline-light">Add Now</a></div>';
                                                } else {
                                                    echo '<div class="alert text-bg-dark text-center m-0">Last Spin Upload: '.$interval->days.' days ago</div>';
                                                }
                                            }
                                        }
                                   ?>
                               </div>
                               <div class="col text-end">
                                   <?php if($loggedInUser):?>
                                       <?php if($loggedInUser['RoleId']===3):?>
                                           <div class="dropdown">
                                               <button class="btn btn-dark dropdown-toggle no-loading" type="button"
                                                       id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                                   Manage Profile
                                               </button>
                                               <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>artists/edit/releases">Releases</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>artists/edit/shows">Shows</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>artists/edit/videos">Videos</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>artists/edit/connections">Connections</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>artists/edit/profile">Profile</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>logout">Logout</a></li>
                                               </ul>
                                           </div>
                                       <?php endif;?>
                                       <?php if($loggedInUser['RoleId']===7):?>
                                           <div class="dropdown">
                                               <button class="btn btn-dark dropdown-toggle no-loading" type="button"
                                                       id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                                   Manage Profile
                                               </button>
                                               <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>labels/edit-posts">Posts</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>labels/edit-artists">Artists</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>labels/edit-releases">Releases</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>labels/edit-videos">Videos</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>labels/edit-connections">Connections</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>labels/edit-profile">Profile</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>logout">Logout</a></li>
                                               </ul>
                                           </div>
                                       <?php endif;?>

                                       <?php if($loggedInUser['RoleId']===9):?>
                                           <div class="dropdown">
                                               <button class="btn btn-dark dropdown-toggle no-loading" type="button"
                                                       id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                                   Manage Profile
                                               </button>
                                               <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>stations/edit/profile">Edit Profile</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>stations/edit/spins">Spins</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>stations/edit/posts">Posts</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>stations/edit/connections">Connections</a></li>
                                                   <li><a class="dropdown-item" href="<?=$GLOBALS['Default']['Baseurl'];?>stations/edit/settings">Settings</a></li>
                                                   <li><a class="dropdown-item" href="#">Logout</a></li>
                                               </ul>
                                           </div>
                                       <?php endif;?>
                                   <?php endif;?>
                               </div>
                           </div>
                       </div>

                   <?php endif;?>
               </div>
           </div>
       </div>
    </div>
    <?php endif;?>
    <div class="container-fluid py-2">
        <div class="row">
            <div class="col">
                <div class="d-none d-md-block">
                    <ul class='nav justify-content-end small'>
                        <li class='nav-item'><a href='<?=$GLOBALS['Default']['Baseurl'];?>donations' class='btn btn-dark btn-sm no-loading'><i class='bi bi-gift'></i> Donate</a></li>
                        <?php if($authenticated):?>
                            <li class="nav-item"><a href="<?=$GLOBALS['Default']['Baseurl'];?>logout" class="btn btn-dark btn-sm"><i class='bi bi-key'></i> Logout</a></li>
                        <?php else:?>
                            <li class='nav-item'><a href='<?= $GLOBALS['Default']['Baseurl']; ?>login'
                                                    class='btn btn-dark btn-sm'><i class='bi bi-key-fill'></i> Login</a></li>
                        <?php endif;?>
                        <li class='nav-item dropdown'>
                            <button class='btn btn-sm btn-dark dropdown-toggle no-loading' data-bs-toggle='dropdown'
                                    aria-expanded='false'>
                                Register
                            </button>
                            <ul class='dropdown-menu dropdown-menu-dark'>
                                <li><a class='dropdown-item' href='<?=$GLOBALS['Default']['Baseurl'];?>register/station'>Radio Station</a></li>
                                <li><a class='dropdown-item disabled' href='#'>Record Label</a></li>
                                <li><a class='dropdown-item disabled' href='#'>Management/Agent</a></li>
                                <li><a class='dropdown-item disabled' href='#'>Media (Other)</a></li>
                            </ul>
                        </li>
                        <li class='nav-item'><a href='<?= $GLOBALS['Default']['Baseurl']; ?>advertising'
                                                class='btn btn-dark btn-sm no-loading'><i class='bi bi-megaphone'></i>
                                Advertise</a></li>
<!--                        <li class='nav-item'><a href='#' class='btn btn-dark btn-sm no-loading' disabled><i-->
<!--                                        class='bi bi-question-circle'></i> Help</a></li>-->
                    </ul>
                </div>
            </div>
        </div>
        <div class='row align-items-center'>
            <div class='col-4 col-lg-2 col-xl-2'>
                <a href="<?= $GLOBALS['Default']['Baseurl']; ?>"><img
                            src="<?= $GLOBALS['Default']['Baseurl']; ?>lib/images/site/web-light-1.png"
                            alt="<?= $GLOBALS['Default']['Author']; ?>" class='img-fluid w-100'></a>
            </div>
            <div class='col col-lg-9 col-xl-8 d-none d-lg-inline-block'>
                <ul class='nav flex-row justify-content-center'>
                    <li class='nav-item'><a href='<?=$GLOBALS['baseurl'];?>news'
                                            class='btn btn-sm btn-dark me-2 py-2 px-4 btn-lg'>News</a>
                    </li>
                    <li class='nav-item'><a href='<?=$GLOBALS['baseurl'];?>reviews'
                                            class='btn btn-sm btn-dark me-2 py-2 px-4 btn-lg'>Reviews</a>
                    </li>
                    <li class='nav-item'><a href='<?=$GLOBALS['baseurl'];?>videos'
                                            class='btn btn-sm btn-dark me-2 py-2 px-4 btn-lg'>Videos</a>
                    </li>
                    <li class='nav-item'><a href='<?=$GLOBALS['baseurl'];?>charts'
                                            class='btn btn-sm btn-dark me-2 py-2 px-4 btn-lg'>NGN Charts</a>
                    </li>
                    <li class='nav-item'><a href='<?=$GLOBALS['baseurl'];?>smr-charts'
                                            class='btn btn-sm btn-dark me-2 py-2 px-4 btn-lg'>SMR Charts</a>
                    </li>

                    <li class='nav-item'><a href='<?=$GLOBALS['baseurl'];?>about'
                                            class='btn btn-sm btn-dark me-2 py-2 px-4 btn-lg'>About</a>
                    </li>

                </ul>
            </div>
            <div class='col-8 col-lg-1 col-xl-2 align-items-center text-end'>
                <ul class='nav d-flex justify-content-end px-0 py-2 m-0 h-100'>


                    <li class='nav-item'>
                        <a href='#' class='btn btn-lg text-white d-block no-loading'
                           data-bs-toggle="offcanvas"
                           data-bs-target="#search"
                           aria-controls="search"><i class='bi bi-search fs-4'></i></a>
                    </li>

                    <li class='nav-item'>
                        <button class='btn btn-lg d-lg-none no-loading text-white' type='button' data-bs-toggle='offcanvas'
                                data-bs-target='#mainMenu' aria-controls='mainMenu'>
                            <i class='bi bi-three-dots-vertical fs-4'></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>

    </div>
    <div class='offcanvas offcanvas-end' tabindex='-1' id='mainMenu' aria-labelledby='mainMenuLabel'>
        <div class='offcanvas-header'>
            <h5 class='offcanvas-title' id='mainMenuLabel'>Main Menu</h5>
            <button type='button' class='btn-close' data-bs-dismiss='offcanvas' aria-label='Close'></button>
        </div>
        <div class='offcanvas-body'>
            <ul class='nav flex-column'>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>smr-charts" class='nav-link'>SMR Charts</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>charts" class='nav-link'>NGN Charts</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>news" class='nav-link'>News</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>reviews" class='nav-link'>Reviews</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>videos" class='nav-link'>Videos</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>about" class='nav-link'>About</a></li>
                <li class='nav-item'><a href="<?= $GLOBALS['Default']['Baseurl']; ?>about/writers" class='nav-link'>Our Writers</a></li>
                <li class="nav-item"><hr class="dropdown-divider"></li>
                <li class="nav-item"><a href="<?= $GLOBALS['Default']['Baseurl']; ?>login" class="nav-link">Login</a></li>
                <li class="nav-item"><a href="<?= $GLOBALS['Default']['Baseurl']; ?>donations" class="nav-link">Donate</a></li>
                <li class="nav-item"><a href="<?= $GLOBALS['Default']['Baseurl']; ?>advertising" class="nav-link">Advertise</a></li>
                <li class="nav-item"><hr class="border"></li>

                <li class='nav-item'><a href='<?=$GLOBALS['Default']['Baseurl'];?>register/station' class='btn btn-primary d-block w-100'>Register Station</a>
            </ul>
        </div>
    </div>
    <!-- DONATIONS AD -->
<!--    <a href="--><?php //= $GLOBALS['Default']['Baseurl']; ?><!--donations">-->
<!--        <img src="--><?php //=$GLOBALS['Default']['Baseurl'];?><!--lib/images/ads/donate-now-1/desktop.jpg" alt="" class="d-none d-md-block img-fluid w-100">-->
<!--        <img src="--><?php //=$GLOBALS['Default']['Baseurl'];?><!--lib/images/ads/donate-now-1/mobile.jpg" alt="" class="d-md-none img-fluid w-100">-->
<!--    </a>-->
    <div class='offcanvas offcanvas-top' tabindex='-1' id='search' aria-labelledby='searchLabel' style="height: 400px;">
        <div class='offcanvas-header'>
            <h5 class='offcanvas-title' id='searchLabel'>Search</h5>
            <button type='button' class='btn-close' data-bs-dismiss='offcanvas' aria-label='Close'></button>
        </div>
        <div class='offcanvas-body'>
            <input type="text" id="searchCanvas" class="form-control form-control-lg" placeholder="Type to begin searching">
            <div id="searchResultsCanvas"></div>
        </div>
    </div>


</header>

<?php
// get current advertisement

$advertisement = false;
$activeAds = [];
$advertisements = readMany('Ads','Active',1);
$advertisements = array_reverse($advertisements);
// example startDate = 2025-02-23 00:00:00
// example endDate = 2025-03-03 00:00:00
foreach ($advertisements as $ad) {
    $startDate = isset($ad['StartDate']) ? strtotime($ad['StartDate']) : false;
    $endDate = isset($ad['EndDate']) ? strtotime($ad['EndDate']) : false;
    $now = time(); // Use current timestamp

    if ($startDate !== false && $endDate !== false && $startDate <= $now && $endDate >= $now) {
        $advertisement = true;
    }
        $activeAds[] = $ad;
}

?>


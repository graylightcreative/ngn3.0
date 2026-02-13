<?php if(isset($advertisement)):?>
    <div class="single-carousel">
        <?php if(count($activeAds)>0):?>
            <?php foreach($activeAds as $key => $ad):?>
                <?php if($ad):?>
                    <div>
                        <a href="<?= $ad['Url']; ?>" title="<?= $ad['Title']; ?>" class="advertisement-link d-inline-block w-100">
                            <img src="<?= $GLOBALS['Default']['Baseurl']; ?>lib/images/ads/<?= $ad['Slug']; ?>/<?= $ad['ContentAdHorizontal']; ?>" alt='' class='img-fluid w-100 d-none d-lg-block'>
                            <img src="<?= $GLOBALS['Default']['Baseurl']; ?>lib/images/ads/<?= $ad['Slug']; ?>/<?= $ad['ContentAdHorizontalMobile']; ?>" alt='' class='img-fluid w-100 d-lg-none'>
                        </a>
                    </div>
                <?php endif;?>
            <?php endforeach;?>
        <?php endif;?>
    </div>
<?php endif;?>

<footer class='bg-black py-5 text-light'>
    <div class="container">
        <div class="row">
            <div class="col-md-10 mx-auto">
                <div class="row mb-3">
                    <div class="col text-center">
                        <div class="small">NextGen Noise is a live construction zone! We're building the future of music every day, so expect some dust and the occasional power tool mishap. If you hit a snag, shoot an email to info@nextgennoise.com.
                        <a href="<?=$baseurl;?>notes">View Development Notes</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr>
	<div class='container-fluid mt-5'>

        <div class="row mb-3 d-none">
            <div class="col text-center">
                <?php $donations = browse('Donations');?>
                <?php if($donations):?>
                Donations: <?=count($donations);?>
                <?php else:?>
                    <div class="small">Currently no donations have been made. Help The SMR Bands/Labels Today by <a href="<?=$GLOBALS['Default']['Baseurl'];?>donations" class="text-light">Donating</a></div>
                <?php endif;?>

            </div>
        </div>
		<div class='row'>
			<div class='col text-center'>
				<a href="<?=$GLOBALS['Default']['Baseurl'];?>" title="<?= $GLOBALS['Default']['Title']; ?>"><img
						src="<?=$GLOBALS['Default']['Baseurl'];?>lib/images/site/2026/NGN-Stacked-Full-Light.png"
						alt="<?= $GLOBALS['Default']['Title']; ?>" class='img-fluid' style='max-width: 200px; height: auto;'></a>
				<ul class='nav justify-content-center'>

				</ul>

				<p class="small">
					Copyright <?= date('Y'); ?> NextGen Noise
                    <br>
                    <a href="<?=$GLOBALS['Default']['Baseurl'];?>privacy-policy" title="Read our Privacy Policy">Privacy Policy</a> |
                    <a href="<?=$GLOBALS['Default']['Baseurl'];?>terms-of-service" title="Read our Terms of Service">Terms of Service</a>
				</p>
                <p class="text-light ">
                    <a href="https://graylightcreative.com" title="Graylight Creative"><img
                                src="https://graylightcreative.com/lib/images/graylight-creative-logo-dark.png" alt="Graylight Creative" width="100px"></a>
                </p>
			</div>
		</div>
	</div>
</footer>

<div class="popup" id="donatePopup">
    <button class="btn btn-sm btn-round close-popup"><i class="bi bi-x"></i></button>
    <h2>Donate to The NGN</h2>
    <div class='btn-group' role='group' aria-label='Donation Amounts'>
        <input type='radio' class='btn-check' name='donation_amount' id='option-5' autocomplete='off' data-amount='500'
               checked>
        <label class='btn btn-outline-primary no-loading' for='option-5'>$5</label>

        <input type='radio' class='btn-check' name='donation_amount' id='option-10' autocomplete='off'
               data-amount='1000'>
        <label class='btn btn-outline-primary no-loading' for='option-10'>$10</label>

        <input type='radio' class='btn-check' name='donation_amount' id='option-25' autocomplete='off'
               data-amount='2500'>
        <label class='btn btn-outline-primary no-loading' for='option-25'>$25</label>

        <input type='radio' class='btn-check' name='donation_amount' id='option-50' autocomplete='off'
               data-amount='5000'>
        <label class='btn btn-outline-primary no-loading' for='option-50'>$50</label>

        <input type='radio' class='btn-check' name='donation_amount' id='option-100' autocomplete='off'
               data-amount='10000'>
        <label class='btn btn-outline-primary no-loading' for='option-100'>$100</label>

        <input type='radio' class='btn-check' name='donation_amount' id='option-200' autocomplete='off'
               data-amount='20000'>
        <label class='btn btn-outline-primary no-loading' for='option-200'>$200</label>

        <input type='radio' class='btn-check' name='donation_amount' id='option-500' autocomplete='off'
               data-amount='50000'>
        <label class='btn btn-outline-primary no-loading' for='option-500'>$500</label>

        <input type='radio' class='btn-check' name='donation_amount' id='option-custom' autocomplete='off' data-amount="custom">
        <label class='btn btn-outline-primary no-loading' for='option-custom'>Custom</label>


    </div>
    <div class="text-center mt-3">
        <input type="email" class="form-control" name="email" id="donateEmail" placeholder="youremail@domain.com">
        <button id='donate-checkout' class='btn btn-primary mt-3 px-5 d-block w-100'><i class="bi bi-stripe"></i> Donate Now</button>
        <hr class="border border-secondary">
        <p style="font-size:.8rem;">
            All donations handled by <i class="bi bi-stripe"></i> Stripe for Secure Transactions
            <br>
            and will be 100% used for marketing and furthering the reach of The NGN!
            <br>
            All payments will be tracked by our parent company, Graylight Creative
        </p>
    </div>
</div>
<!-- TEMPORARY: Modal shows to ALL users while we debug authentication -->
<div class="popup" id="contactJoinPopup">
    <button class="close-popup btn btn-sm"><i class="bi bi-x"></i></button>
    <div class="container">
        <div class="row">
            <div class="col">
                <form action='' method='post' novalidate class='newsletterSignup'>
                    <div class='row'>
                        <div class='col'>
                            <h2>🤘 Join the NGN Movement!</h2>
                            <p>
                                Don't miss a beat of the underground music scene. Sign up for the NextGen Noise newsletter and get:
                            </p>
                            <ul>
                                <li><span class="text-primary">Exclusive chart insights:</span> Deep dives into the data that reveal the hottest rising artists and trends.</li>
                                <li><span class="text-primary">Curated music picks:</span> Discover the bands you need to know before they hit the mainstream.</li>
                                <li><span class="text-primary">Behind-the-scenes stories:</span> Get closer to the artists and the music that fuels your soul.</li>
                                <li><span class="text-primary">Launch updates:</span> Be the first to know about new features, exclusive content, and community events.</li>
                            </ul>
                            <p>
                                Enter your email below and crank up the volume on your music discovery journey!
                            </p>
                        </div>
                    </div>
                    <div class='row'>
                        <div class='col-md-6 mb-3'>
                            <div class='form-floating'>
                                <input type='text' class='form-control newsletterFirstName'
                                       placeholder='first name' value='' required>
                                <label for='newletterFirstName'>First Name <span
                                            class='text-danger'>*</span></label>
                            </div><!-- // First -->
                        </div>
                        <div class='col-md-6 mb-3'>
                            <div class='form-floating'>
                                <input type='text' class='form-control newsletterLastName'
                                       placeholder='last name'
                                       value='' required>
                                <label for='newletterLastName'>Last Name <span
                                            class='text-danger'>*</span></label>
                            </div><!-- // Last -->
                        </div>
                    </div>
                    <div class='row'>
                        <div class='col-md-6 mb-3'>
                            <div class='form-floating'>
                                <input type='email' class='form-control newsletterEmail'
                                       placeholder='name@example.com' value='' required>
                                <label for='newletterEmail'>Email <span class='text-danger'>*</span></label>
                            </div><!-- // Email -->
                        </div>
                        <div class='col-md-6 mb-3'>
                            <div class='form-floating'>
                                <input type='tel' class='form-control newsletterPhone'
                                       placeholder='0000000000' value=''>
                                <label for='newsletterPhone'>Mobile Phone</label>
                            </div><!-- // Phone -->
                        </div>
                    </div>
                    <div class='row'>
                        <div class='col-md-6 mb-3'>
                            <div class='form-floating'>
                                <input type='date' class='form-control newsletterBirthday' value='' required>
                                <label for='newsletterBirthday'>Birthday <span class='text-danger'>*</span></label>
                            </div><!-- // Birthday -->
                        </div>
                        <div class='col-md-6 mb-3'>
                            <div class='form-floating'>
                                <input type='text' class='form-control newsletterBand'
                                       placeholder='Band Name Here' value='' required>
                                <label for='newsletterBand'>Favorite Band <span
                                            class='text-danger'>*</span></label>
                            </div><!-- // Band -->
                        </div>
                    </div>
                    <button class='btn btn-lg btn-primary d-block w-100 btn-round mt-2'>Get Exclusive Access</button>
                    <button type='button' class='btn btn-link d-block w-100 mt-2 dismiss-popup'>Not now</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="footerMenu" class="text-bg-dark">

    <div class="text-center mb-2 ">
        <button class="btn btn-sm btn-light py-1 no-loading" id="footerMenuExpand" style="position: absolute; top: -20px; right: 10px;">
            <i class="bi bi-chevron-up tiny"></i>
        </button>
    </div>
    <ul class="nav d-flex justify-content-center pb-1">
        <li class="nav-item flex-fill">
            <a href="<?=$GLOBALS['Default']['Baseurl'];?>artists" class="btn btn-primary btn-sm no-loading w-100 py-2" style="line-height:1;"><span class="small">Artists</span><br><span class="fs-6"><?=$_SESSION['artistCount'];?></span></a>
        </li>
        <li class="nav-item flex-fill">
            <a href="<?=$GLOBALS['Default']['Baseurl'];?>labels" class="btn btn-primary btn-sm no-loading d-inline-block w-100 py-2" style="line-height:1;"><span class="small">Labels</span><br><span class="fs-6"><?=$_SESSION['labelCount'];?></span></a>
        </li>
        <li class="nav-item flex-fill">
            <a href="<?=$GLOBALS['Default']['Baseurl'];?>venues" class="btn btn-primary btn-sm no-loading w-100 py-2" style="line-height:1;"><span class="small">Venues</span><br><span class="fs-6"><?=$_SESSION['venuesCount'];?></span></a>
        </li>
        <li class="nav-item flex-fill">
            <a href="<?=$GLOBALS['Default']['Baseurl'];?>stations" class="btn btn-primary btn-sm no-loading w-100 py-2" style="line-height:1;"><span class="small">Stations</span><br><span class="fs-6"><?=$_SESSION['stationCount'];?></span></a>

        </li>
    </ul>

    <div id="footerStats" class="p-5" style="display:none;">
        
        <div class="row">
            <div class="col-12">
                <h3>Stats</h3>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <p class="tiny">This section will soon display detailed statistics and analytics, providing insights
                    into the performance of artists, labels, venues, and stations. Stay tuned for upcoming features!</p>
            </div>
        </div>
    </div>
</div>


<!-- NGN Player Styles -->
<link rel="stylesheet" href="<?=$GLOBALS['Default']['Baseurl'];?>public/css/player.css">

<!-- NGN Player Container -->
<div id="ngn-player-container"></div>

<script src='https://code.jquery.com/jquery-3.7.1.min.js'></script>

<script src='https://cdnjs.cloudflare.com/ajax/libs/jquery-throttle-debounce/1.1/jquery.ba-throttle-debounce.min.js'
        integrity='sha512-JZSo0h5TONFYmyLMqp8k4oPhuo6yNk9mHM+FY50aBjpypfofqtEWsAgRDQm94ImLCzSaHeqNvYuD9382CEn2zw=='
        crossorigin='anonymous' referrerpolicy='no-referrer'></script>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
        integrity='sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz'
        crossorigin='anonymous'></script>


<script src='https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js'></script>
<script src="<?=$GLOBALS['Default']['Baseurl'];?>lib/js/site.js?v=<?=strtotime('now');?>"></script>
<script src='https://js.stripe.com/v3/'></script>
<script src="<?=$GLOBALS['Default']['Baseurl'];?>lib/js/donations.js?v=<?=strtotime('now');?>"></script>

<!-- Expose authentication state BEFORE newsletter-signup loads (it checks window.NGN.isAuthenticated) -->
<script>
    window.NGN = window.NGN || {};
    window.NGN.isAuthenticated = <?php echo $authenticated ? 'true' : 'false'; ?>;
</script>

<script src="<?=$GLOBALS['Default']['Baseurl'];?>lib/js/newsletter-signup.js?v=<?=strtotime('now');?>"></script>
<script src="<?=$GLOBALS['Default']['Baseurl'];?>lib/js/search.js?v=<?=strtotime('now');?>"></script>
<script type="text/javascript" src="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>


<script src="<?=$GLOBALS['Default']['Baseurl'];?>lib/js/carousel.js?v=<?=strtotime('now');?>"></script>

<!-- NGN Player Initialization -->
<script type="module" src="<?=$GLOBALS['Default']['Baseurl'];?>public/js/player/player-init.js"></script>

<script>
    // Enable dark mode by default
    document.documentElement.classList.add('dark');
    $('#footerMenuExpand').on('click', function () {
        $('#footerStats').slideToggle();
    })
</script>

</body>
</html>
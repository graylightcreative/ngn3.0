<!doctype html>

<?php if($GLOBALS['theme']['light']):?>
<html lang='en' data-bs-theme='light'>
<?php else:?>
<html lang='en' data-bs-theme='dark'>
<?php endif;?>
<head>
    <?php
    if(!isset($pageSettings)){
        $pageSettings = $GLOBALS['Default'];
        $pageSettings['Url'] = $baseurl;
    }

    ?>

    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title><?=htmlspecialchars($pageSettings['Title']);?></title>

    <meta name='description' content="<?=htmlspecialchars($pageSettings['Summary']);?>">
    <meta name='keywords' content='<?=$pageSettings['Tags'];?>'>
    <meta name='author' content='<?=$pageSettings['Author'];?>'>
    <meta name='robots' content='index, follow'>

    <meta property='og:title' content='<?=htmlspecialchars($pageSettings['Title']);?>'>
    <meta property='og:description' content="<?=htmlspecialchars($pageSettings['Summary']);?>">
    <meta property='og:image' content='<?=$pageSettings['Image'];?>'>
    <meta property='og:url' content='<?=$pageSettings['Url'];?>'>
    <meta property='og:type' content='website'>

    <meta name='twitter:card' content='<?=$GLOBALS['Default']['Image'];?>'>
    <meta name='twitter:title' content='<?=htmlspecialchars($pageSettings['Title']);?>'>
    <meta name='twitter:description' content="<?=htmlspecialchars($pageSettings['Summary']);?>">
    <meta name='twitter:image' content='<?=$pageSettings['Image'];?>'>

    <link rel='icon' type='image/x-icon' sizes='16x16' href='<?=$GLOBALS['Default']['Baseurl'];?>lib/images/site/favicon-16x16.png'>
    <link rel='icon' type='image/x-icon' sizes='32x32' href='<?=$GLOBALS['Default']['Baseurl'];?>lib/images/site/favicon-32x32.png'>

    <link rel='apple-touch-icon' sizes='180x180' href='<?=$GLOBALS['Default']['Baseurl'];?>lib/images/site/apple-touch-icon.png'>

    <link rel='manifest' href='<?=$GLOBALS['Default']['Baseurl'];?>lib/images/site/site.webmanifest'>
    <link rel='mask-icon' href='<?=$GLOBALS['Default']['Baseurl'];?>lib/images/site/safari-pinned-tab.svg' color='<?=$GLOBALS['theme']['color'];?>'>
    <meta name='msapplication-TileColor' content='<?=$GLOBALS['theme']['color'];?>'>

    <meta name='theme-color' content='<?=$GLOBALS['theme']['color'];?>'>
    <meta http-equiv='Content-Type' content='text/html'>
    <meta name='referrer' content='no-referrer-when-downgrade'>

    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'
          integrity='sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH' crossorigin='anonymous'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Place this CSS in your own stylesheet or in a <style> tag within your HTML's <head> */
        :root {
            --bs-primary: <?=$GLOBALS['theme']['color'];?>;
            --bs-primary-rgb: <?=$GLOBALS['theme']['rgb'][0];?>,<?=$GLOBALS['theme']['rgb'][1];?>,<?=$GLOBALS['theme']['rgb'][2];?>;
        }
        .btn-primary,
        .bg-primary,
        .text-primary,
        .border-primary {
            --bs-btn-bg: <?=$GLOBALS['theme']['color'];?>; !important; /* Custom primary color for buttons */
            --bs-btn-border-color: <?=$GLOBALS['theme']['color'];?>; !important; /* Custom primary border color for buttons */
            --bs-btn-hover-bg: <?=$GLOBALS['theme']['shades']['darker'];?>; !important;  /* Custom hover color (slightly darker) for buttons */
            --bs-btn-hover-border-color: <?=$GLOBALS['theme']['shades']['darker'];?>; !important;  /* Custom hover border color (slightly darker) for buttons */
            --bs-btn-focus-shadow-rgb: 0, 0, 0 !important; /* Adjust focus shadow color (optional) */
        }
        .btn-outline-primary {
            --bs-btn-color: <?=$GLOBALS['theme']['color'];?>;; /* Your chosen hex color */
            --bs-btn-border-color: <?=$GLOBALS['theme']['color'];?>;;
            --bs-btn-hover-color: #fff; /* Adjust hover text color if needed */
            --bs-btn-hover-bg: <?=$GLOBALS['theme']['color'];?>;;
            --bs-btn-hover-border-color: <?=$GLOBALS['theme']['color'];?>;;
            --bs-btn-focus-shadow-rgb: 0,0,0; /* Focus shadow (optional) */
            --bs-btn-active-color: #fff; /* Active text color (optional) */
            --bs-btn-active-bg: <?=$GLOBALS['theme']['color'];?>;;
            --bs-btn-active-border-color: <?=$GLOBALS['theme']['color'];?>;;
            --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125); /* Active shadow (optional) */
            --bs-btn-disabled-color: <?=$GLOBALS['theme']['color'];?>;; /* Disabled text color (optional) */
            --bs-btn-disabled-bg: transparent;
            --bs-btn-disabled-border-color: <?=$GLOBALS['theme']['color'];?>;;
        }
        /* Add this if you also want to change the link color */
        a:not(.btn,.dropdown-item) {
            color: <?=$GLOBALS['theme']['color'];?>; !important; /* Custom primary color for links */
            /*color: #f51b1f !important; !* Custom primary color for links *!*/
        }
        a:not(.btn,.dropdown-item):hover {
            color: <?=$GLOBALS['theme']['shades']['darker'];?> !important;
        }

        .btn-primary:disabled,
        .btn-outline-primary:disabled,
        a:not(.btn,.dropdown-item):disabled {
            background-color: <?=$GLOBALS['theme']['color'];?> !important; /* Custom primary color for disabled buttons and links */
        }

    </style>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick.css'
          integrity='sha512-wR4oNhLBHf7smjy0K4oqzdWumd+r5/+6QO/vDda76MW5iug4PT7v86FoEkySIJft3XA0Ae6axhIvHrqwm793Nw=='
          crossorigin='anonymous' referrerpolicy='no-referrer'/>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick-theme.css'
          integrity='sha512-6lLUdeQ5uheMFbWm3CP271l14RsX1xtx+J5x2yeIDkkiBpeVTNhTqijME7GgRKKi6hCqovwCoBTlRBEC20M8Mg=='
          crossorigin='anonymous' referrerpolicy='no-referrer'/>    <link rel="stylesheet" href="<?=$GLOBALS['baseurl'];?>lib/css/ngn.css?v=<?=strtotime('now');?>">

    
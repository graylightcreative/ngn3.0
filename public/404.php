<?php
$root = './';
require $root.'lib/definitions/site-settings.php';

// Set our page SEO meta
$pageSettings = array();
$pageSettings['Title'] = "404 Page Not Found | " . $GLOBALS['Default']['Title'];
$pageSettings['Tags'] = '404, Not Found, You are lost';
$pageSettings['Summary'] = 'Sometimes on this musical journey, you get lost.';
// get author
$pageSettings['Author'] = $GLOBALS['Default']['Author'];
$pageSettings['Url'] = $GLOBALS['baseurl'];
$pageSettings['Image'] = $GLOBALS['Default']['Image'];

require $root.'lib/partials/head.php';
?>
</head>
<body>
<?php require $root.'lib/partials/header.php';?>
<main class="py-5">
    <div class='container'>
        <div class='row'>
            <div class="col-md-8 mx-auto text-center">
                <h1>404 | WOMP WOMP :(</h1>
                <hr class="border">
                <p class="h4 text-muted">Sometimes in this world of music we find ourselves lost.</p>

                <p class="h1">Right now, that's you!</p>

                <p>
                    Page not found
                </p>

            </div>
        </div>

    </div>
</main>

<?php require $root.'lib/partials/footer.php';?>
</body>
</html>
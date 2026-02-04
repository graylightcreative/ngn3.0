<footer class='bg-black py-5'>
	<div class='container-fluid'>
		<div class='row'>
			<div class='col text-center'>
                <a href="<?= $GLOBALS['Default']['Baseurl']; ?>">Home</a> |
                <a href="<?= $GLOBALS['Default']['Baseurl']; ?>logout">Logout</a>
			</div>
		</div>
	</div>
</footer>

<script src='https://code.jquery.com/jquery-3.7.0.min.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/jquery-throttle-debounce/1.1/jquery.ba-throttle-debounce.min.js'
        integrity='sha512-JZSo0h5TONFYmyLMqp8k4oPhuo6yNk9mHM+FY50aBjpypfofqtEWsAgRDQm94ImLCzSaHeqNvYuD9382CEn2zw=='
        crossorigin='anonymous' referrerpolicy='no-referrer'></script>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js'></script>
<script type='text/javascript' src='https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js'></script>
<!-- Latest compiled and minified JavaScript -->
<script src='https://cdn.datatables.net/2.0.8/js/dataTables.js'></script>
<script src='https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.js'></script>
<script type='text/javascript' src='<?= $GLOBALS['baseurl']; ?>lib/richtexteditor/rte.js'></script>
<script type='text/javascript' src='<?= $GLOBALS['baseurl']; ?>lib/richtexteditor/plugins/all_plugins.js'></script>
<script src="<?= $GLOBALS['baseurl']; ?>lib/js/site.js?v=<?=strtotime('now');?>"></script>
<script>
    // Enable dark mode by default
    document.documentElement.classList.add('dark');
</script>
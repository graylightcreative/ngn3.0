<?php
    $root = $_SERVER['DOCUMENT_ROOT'].'/';
    $adminRoot = $root.'admin/';
    require $root.'lib/definitions/site-settings.php';
    require $adminRoot.'lib/definitions/admin-settings.php';
    require $root.'lib/partials/head.php';

    $items = browse('ApiKeys');
?>
</head>
<body>
<?php require '../lib/partials/header.php';?>
<main class="py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-8">
                <h1>API Keys</h1>
            </div>
            <div class="col-4 text-end">
                <a href="add" class="btn btn-sm btn-primary">Add Key</a>
            </div>
        </div>
        <div class="row">
            <div class="col">

                <table class="table table-striped w-100 small" id="data">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Key</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if($items):?>
                    <?php foreach($items as $item):?>
                        <tr>
                            <td><?=$item['Id'];?></td>
                            <td><?=$item['UserId'];?></td>
                            <td><?=returnNormalDate($item['Created'])?></td>
                            <td>
                                <?=$item['Value'];?>
                            </td>
                            <td>
                                <div class='dropdown'>
                                    <button class='btn btn-secondary dropdown-toggle' type='button'
                                            data-bs-toggle='dropdown' aria-expanded='false'>
                                        <i class="bi bi-gear"></i>
                                    </button>
                                    <ul class='dropdown-menu'>
                                        <li><button class='dropdown-item delete' data-id=<?=$item['Id'];?>>Delete</button></li>

                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach;?>
                    <?php else:?>
                    <tr><td colspan=5>No results to display</td></tr>
                    <?php endif;?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php require '../lib/partials/footer.php';?>
<script>
    new DataTable('#data', {
        order: [[1,'desc']]
    })
</script>
</body>
</html>
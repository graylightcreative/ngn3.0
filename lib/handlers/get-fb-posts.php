<?php

$root = $_SERVER['DOCUMENT_ROOT'] . '/';
require $root.'lib/definitions/site-settings.php';

$response = new Response();


if(!isset($_SESSION['User'])) $response->killWithMessage($response->message="No session data available");
$user = read('users','Id',$_SESSION['User']['Id']);
if(!$user) $response->killWithMessage($response->message="Could not find user");
$fbPageToken = read('Tokens','UserId',$user['Id']);
if(!$fbPageToken) $response->killWithMessage($response->message="This user does not have a valid page token");

if($fbPageToken){
    $fb = new Facebook();
    $fb->token = $fbPageToken['Token'];
    $fb->pageId = $fbPageToken['PageId'];
    $requiredScopes = ['read_insights', 'pages_read_engagement'];

    if ($fb->hasScopes($requiredScopes)) {
        $posts = $fb->getPagePosts(25);
        if($posts):
            foreach($posts as $key => $post):

                $media = $fb->getPostMedia($post['id']);
                if ($media) {
                    $mediaAttachments = [];
                    foreach ($media as $item) {
                        if (isset($item['media'])) {
                            $mediaAttachments[] = $item['media']['image']['src']; // For an image
                        } elseif (isset($item['subattachments'])) {
                            foreach ($item['subattachments']['data'] as $subItem) {
                                $mediaAttachments[] = $subItem['media']['image']['src'];
                            }
                        }
                    }
                }
                $content = '';
                if(isset($mediaAttachments[0]) && !empty(trim($mediaAttachments[0]))):?>

                    <div class="col mb-4">
                        <div class="card">
                            <img src="<?=$mediaAttachments[0];?>" class="card-img-top" alt="...">
                            <div class="card-body">
                                <div class="card-text">
                                    <i class="bi bi-facebook"></i> | <?=date('m/d/Y h:ia', strtotime($post['created_time']));?>
                                    <hr>
                                    <?php if(isset($post['message'])):?>
                                        <?=$post['message'];?>
                                    <?php elseif(isset($post['story'])):?>
                                        <?=$post['story'];?>
                                    <?php else:?>
                                        <?='No message or story';?>
                                    <?php endif; ?>
                                    <hr>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif;
            endforeach;
        endif;
    } else {
        $response->killWithMessage($response->message="Token does not have the require scopes");
    }
}



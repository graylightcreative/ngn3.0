<?php

function createPostItem($post,$type,$columnSettings='col-md-6 col-lg-4 col-xl-3'){

	$item = '<div class="col-md-6 '.$columnSettings.'">';
	$item .= "<a href='{$GLOBALS['Default']['Baseurl']}{$type['Section']}/{$post['Slug']}' title='Read {$post['Title']} now!'>";
	$item .= "<img src='{$GLOBALS['baseurl']}lib/images/posts/{$post['Image']}' alt='{$post['Title']}' class='img-fluid w-100'>";
	$item .= '</a>';
	$item .= '</div>';
	return $item;
}
function createLabelPostItem($post,$slug){

	$item = '<div class="col-md-6 col-lg-4 col-xl-3 mb-3">';
	$item .= "<a href='{$GLOBALS['Default']['Baseurl']}pressers/{$post['Slug']}' title='Read {$post['Title']} now!'>";
	$item .= "<img src='{$GLOBALS['baseurl']}lib/images/posts/{$post['Image']}' alt='{$post['Title']}' class='img-fluid w-100'><br>";
    $item .= $post['Title'];
	$item .= '</a>';
	$item .= '</div>';
	return $item;
}

function createUserListItem($user){
    // what kind of user
    $dept = null;
    switch($user['RoleId']){
        case 3: // artist
            $dept = 'artists';
            break;
        case 7: // label
            $dept = 'labels';
            break;
        case 9: // station
            $dept = 'stations';
            break;
        case 11: // station
            $dept = 'venues';
            break;
    }

    $item = '<div class="col-6 col-md-4 col-lg-4 col-xl-2 mb-3">';
    $item .= '<div class="card border-0">';
    if(empty($user['Image'])):
    $item .= '<a href="'.$GLOBALS['Default']['Baseurl'].$dept.'/'.$user['Slug'].'"><img src="'.$GLOBALS['Default']['Baseurl'].'lib/images/users/default.jpg" alt="Placeholder Image" class="rounded-circle w-100"></a>';
    else:
        $item .= '<a href="'.$GLOBALS['Default']['Baseurl'].$dept.'/'.$user['Slug'].'"><img src="'.$GLOBALS['Default']['Baseurl'].'lib/images/users/'.$user['Slug'].'/'.$user['Image'].'" alt="Placeholder Image" class="rounded-circle w-100"></a>';
    endif;
    $item .= '<div class="card-body text-center">';
    $item .= '<a href="'.$GLOBALS['Default']['Baseurl'].$dept.'/'.$user['Slug'].'" data-type="'.$dept.'"><h4 class="card-heading text-truncate h6 tiny">'.$user['Title'].'</h4></a>';
    $item .= '</div></div></div>';
    return $item;
}

function videoListItem($video,$columnSettings='col-md-6 col-lg-4 col-xl-3')
{
    $item = '<div class="'.$columnSettings.' mb-4">
                    <div class="card">
                        <a href="../videos/'.$video['Slug'].'">
                            <img src="https://img.youtube.com/vi/'.$video['VideoId'].'/0.jpg"
                                 alt="'.$video['Title'].'"
                                 class="img-fluid-top w-100">
                        </a>
                        <div class="card-body">
                            <div class="card-text">
                                <h3 class="h6 tiny card-heading text-truncate">'.$video['Title'].'</h3>
                                <a href="'.$GLOBALS['Default']['Baseurl'].'/artists/'.$video['ArtistSlug'].'" 
                                class="badge border"><span class="tiny">
                                <i class="bi bi-person-fill-up"></i> '.
                                $video['ArtistTitle'].'</span></a>
                            </div>
                        </div>
                    </div>
                </div>';
    return $item;
}
function ReleaseListItem($release,$columnSettings='col-md-6 col-lg-4 col-xl-3')
{
    $item = '<div class="'.$columnSettings.' mb-4">
                    <div class="card">
                        <a href="../releases/'.$release['Slug'].'">
                            <img src="'.$GLOBALS['Default']['Baseurl'].'lib/images/releases/'.$release['ArtistSlug'].'/'.$release['Image'].'"
                                 alt="'.$release['Title'].'"
                                 class="img-fluid-top w-100">
                        </a>
                        <div class="card-body">
                            <div class="card-text">
                                <h3 class="h6 tiny card-heading text-truncate">'.$release['Title'].'</h3>
                                <a href="'.$GLOBALS['Default']['Baseurl'].'/artists/'.$release['ArtistSlug'].'" 
                                class="badge border"><span class="tiny">
                                <i class="bi bi-person-fill-up"></i> '.
                                $release['ArtistTitle'].'</span></a>
                            </div>
                        </div>
                    </div>
                </div>';
    return $item;
}
function createTagsList($tags)
{
    $tags = explode(', ',$tags);
    $list = '';
    foreach ($tags as $tag){
        $list .= '<button class="btn btn-sm btn-outline-primary me-2">'.$tag.'</button>';
    }
    return $list;
}
    
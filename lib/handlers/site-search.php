<?php

$root = '../';
require $root.'definitions/site-settings.php';
require_once $root.'controllers/ResponseController.php';
$_POST = json_decode(file_get_contents('php://input'), true);

$response = makeResponse();

$term = !isset($_POST['term']) ? killWithMessage('No term supplied', $response) : $_POST['term'];

if(strlen($term)>1){
	$term = preg_replace('/[^(\x20-\x7F)]*/','', $term);

// This document is going to take $_POST['term'] and search
// Goal is to have the term split results into 3 sections

// Post Results (Top 5)
	$posts = search('posts','Body',$term);
	if($posts) foreach($posts as $key => $post) {
		$type = read('PostTypes','Id',$post['TypeId']);
		$posts[$key]['type'] = 'post';
		$posts[$key]['url'] = $GLOBALS['Default']['Baseurl'] .$type['Section'].'/'.$post['Slug'];
	}

// Artist Results (Top 5)
	$artists = search('users','Title',$term);
	if($artists) foreach($artists as $key => $artist) {
		if($artist['RoleId'] === 3){
			$artists[$key]['type'] = 'artist';
			$artists[$key]['url'] = $GLOBALS['Default']['Baseurl'].'artists/'.$artist['Slug'];
		} else {
			unset($artists[$key]);
		}
	}
	$artists = array_values($artists);

// Label Results (Top 5)
	$labels = search('users','Title',$term);
	if($labels) foreach($labels as $key => $label) {
		if($label['RoleId'] === 7){
			$labels[$key]['type'] = 'label';
			$labels[$key]['url'] = $GLOBALS['Default']['Baseurl'].'labels/'.$label['Slug'];
		} else {
			unset($labels[$key]);
		}
	}
	$labels = array_values($labels);

	$array = array_merge($posts,$artists,$labels);
	$array = array_unique($array,SORT_REGULAR);
	$array = sortByColumnIndex($array, 'Title');

// It would then be prudent to combine all results and sort by Title
// since we are most likely searching for a post title, artist title, or label title
	$content = '';

	if(count($array)>0){
		foreach($array as $item){
			$content .= '<div class="search-item"><a href="'.$item['url'].'">'.$item['Title'].' <span class="badge bg-primary search-item-type">' . ucwords($item['type']).'</span></a></div>';
		}
	}


	$response['success']= true;
	$response['code'] = 200;
	$response['content'] = $content;
	$response['message'] = 'Results successfully retrieved';
	echo json_encode($response);
}
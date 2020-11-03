<?php
  
try{
  $db = new PDO('mysql:host=localhost;dbname=wordpress', 'root', '');
}
catch (PDOException $e){
  die ("Error connecting to database!");
}

function slug(string $string){
	$string = trim(str_replace([' ','&','%','$',':',',','/','=','?','Ę','Ó','Ą','Ś','Ł','Ż','Ź','Ć','Ń','ę','ó','ą','ś','ł','ż','ź','ć','ń'], ['-','-','-','','','','','','','E','O','A','S','L','Z','Z','C','N','e','o','a','s','l','z','z','c','n'], $string));
	$string = preg_replace("/[^a-zA-Z0-9-_]+/", "", $string);
	$string = trim($string,'-');
	do{
		$string_old = $string;
		$string = str_replace("--", "-", $string);
	}while($string != $string_old);
	return strtolower($string);
}

define('_ADD_TO_ID_CATEGORY_', 10); // możemy dodawać inną stałą do ID kategorii
define('_ADD_TO_ID_POST_', 10); // możemy dodawać inną stałą do ID wpisu

$sth = $db->query('SELECT * FROM categories');

$sth2 = $db->prepare('INSERT INTO `wp_terms`(`term_id`, `name`, `slug`) VALUES (:term_id,:name,:slug)');

$sth3 = $db->prepare('INSERT INTO `wp_term_taxonomy`(`term_taxonomy_id`, `term_id`, `taxonomy`, `description`, `parent`, `count`) VALUES (:term_taxonomy_id,:term_id,"category","",0,0)');

foreach($sth as $row){
	$sth2->bindValue(':term_id', ($row['id']+_ADD_TO_ID_CATEGORY_), PDO::PARAM_INT); 
	$sth2->bindValue(':name', $row['name'], PDO::PARAM_STR);
	$sth2->bindValue(':slug', slug($row['name']), PDO::PARAM_STR);
	$sth2->execute();
	
	$sth3->bindValue(':term_taxonomy_id', ($row['id']+_ADD_TO_ID_CATEGORY_), PDO::PARAM_INT);
	$sth3->bindValue(':term_id', ($row['id']+_ADD_TO_ID_CATEGORY_), PDO::PARAM_INT);
	$sth3->execute();
}

$sth = $db->query('SELECT * FROM articles');

$sth2 = $db->prepare('INSERT INTO `wp_posts`(`ID`, `post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`) VALUES (:ID,1,:post_date,:post_date,:post_content,:post_title,:post_excerpt,"publish","open","open","",:post_name,"","",:post_date,:post_date,"",0,"",0,"post","",0)');

foreach($sth as $row){
	$sth2->bindValue(':ID', ($row['id']+_ADD_TO_ID_POST_), PDO::PARAM_INT);
	$sth2->bindValue(':post_date', date('Y-m-d H:i:s',strtotime($row['date'])), PDO::PARAM_STR);
	$sth2->bindValue(':post_content', $row['content'], PDO::PARAM_STR);
	$sth2->bindValue(':post_title', $row['title'], PDO::PARAM_STR);
	$sth2->bindValue(':post_name', slug($row['title']), PDO::PARAM_STR);
	$sth2->bindValue(':post_excerpt', $row['short'], PDO::PARAM_STR);
	$sth2->execute();
}

$sth = $db->query('SELECT * FROM articles');

$sth2 = $db->prepare('INSERT INTO `wp_term_relationships`(`object_id`, `term_taxonomy_id`) VALUES (:object_id,:term_taxonomy_id)');

foreach($sth as $row){
	$sth2->bindValue(':object_id', ($row['id']+_ADD_TO_ID_POST_), PDO::PARAM_INT); 
	$sth2->bindValue(':term_taxonomy_id', ($row['category_id']+_ADD_TO_ID_CATEGORY_), PDO::PARAM_INT);
	$sth2->execute();
}
	
define('_FOLDER_PHOTOS_OLD_', 'zdjecia/');

define('_FOLDER_PHOTOS_', 'wp-content/uploads/');

$sth = $db->query('SELECT * FROM photos GROUP BY article_id');

$sth2 = $db->prepare('INSERT INTO `wp_posts`(`post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`) VALUES (1,:post_date,:post_date,"",:post_title,"","inherit","open","closed","",:post_title,"","",:post_date,:post_date,"",:post_parent,:guid,0,"attachment","image/jpeg",0)');

$sth3 = $db->prepare('INSERT INTO `wp_postmeta`(`post_id`, `meta_key`, `meta_value`) VALUES (:post_id,"_thumbnail_id",:meta_value)');

$sth4 = $db->prepare('INSERT INTO `wp_postmeta`(`post_id`, `meta_key`, `meta_value`) VALUES (:post_id,"_wp_attached_file",:meta_value)');

$sth5 = $db->prepare('INSERT INTO `wp_postmeta`(`post_id`, `meta_key`, `meta_value`) VALUES (:post_id,"_wp_attachment_metadata",:meta_value)');

foreach($sth as $row){
	
	$path_parts = pathinfo(_FOLDER_PHOTOS_OLD_.$row['url']);
	
	$size  = getimagesize(_FOLDER_PHOTOS_OLD_.$row['url']);
	
	$data = [
		'width' => $size[0],
		'height' => $size[1],
		'file' => $path_parts['basename']
	];

	if(!file_exists(_FOLDER_PHOTOS_.date('Y',strtotime($row['date'])))){
		mkdir(_FOLDER_PHOTOS_.date('Y',strtotime($row['date'])));
	}
	$folder = date('Y',strtotime($row['date'])).'/'.date('m',strtotime($row['date'])).'/';
	if(!file_exists(_FOLDER_PHOTOS_.$folder)){
		mkdir(_FOLDER_PHOTOS_.$folder);
	}

	copy (_FOLDER_PHOTOS_OLD_.$row['url'], _FOLDER_PHOTOS_.$folder.$path_parts['basename'] );

	$sth2->bindValue(':post_date', date('Y-m-d H:i:s',strtotime($row['date'])), PDO::PARAM_STR);
	$sth2->bindValue(':post_title', $path_parts['filename'], PDO::PARAM_STR);
	$sth2->bindValue(':post_parent', ($row['article_id']+_ADD_TO_ID_POST_), PDO::PARAM_INT);
	$sth2->bindValue(':guid', 'http://example.com/wp-content/uploads/'.$folder.$path_parts['basename'], PDO::PARAM_STR);
	$sth2->execute();
	
	$id = $db->lastInsertId();
	
	$sth3->bindValue(':post_id', ($row['article_id']+_ADD_TO_ID_POST_), PDO::PARAM_INT);
	$sth3->bindValue(':meta_value', $id, PDO::PARAM_STR);
	$sth3->execute();
	
	$sth4->bindValue(':post_id', $id, PDO::PARAM_INT);
	$sth4->bindValue(':meta_value', $folder.$path_parts['basename'], PDO::PARAM_STR);
	$sth4->execute();
	
	$sth5->bindValue(':post_id', $id, PDO::PARAM_INT);
	$sth5->bindValue(':meta_value', serialize($data), PDO::PARAM_STR);
	$sth5->execute();
	
}

$sth2 = $db->prepare('UPDATE wp_posts SET post_content=:post_content WHERE ID=:ID limit 1');

$sth3 = $db->prepare('INSERT INTO `wp_posts`(`post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`) VALUES (1,:post_date,:post_date,"",:post_title,"","inherit","open","closed","",:post_title,"","",:post_date,:post_date,"",:post_parent,:guid,0,"attachment","image/jpeg",0)');

$sth = $db->query('SELECT * FROM wp_posts WHERE post_content LIKE \'%<img%src="zdjecia%\'');
foreach($sth as $row){

	$post_content = $row['post_content'];
	
	$doc = new DOMDocument();
	libxml_use_internal_errors(true);
	$doc->loadHTML( $row['post_content'] );
	$xpath = new DOMXPath($doc);
	$imgs = $xpath->query("//img");
	for ($i=0; $i < $imgs->length; $i++) {
		$img = $imgs->item($i);
		$src = $img->getAttribute("src");
		
		if(substr($src, 0, 8)=='zdjecia/'){
			
			if(file_exists($src)){
				$path_parts = pathinfo($src);
				
				copy ($src, _FOLDER_PHOTOS_.$path_parts['basename'] );
				
				$post_content = str_replace($src,"/wp-content/uploads/2020/11/".$path_parts['basename'],$post_content);
			
				$sth2->bindValue(':ID', $row['ID'], PDO::PARAM_INT);
				$sth2->bindValue(':post_content', $post_content, PDO::PARAM_STR);
				$sth2->execute();
				
				$sth3->bindValue(':post_date', date('Y-m-d H:i:s'), PDO::PARAM_STR);
				$sth3->bindValue(':post_title', $path_parts['filename'], PDO::PARAM_STR);
				$sth3->bindValue(':post_parent', $row['ID'], PDO::PARAM_INT);
				$sth3->bindValue(':guid', 'http://example.com/wp-content/uploads/2020/11/'.$path_parts['basename'], PDO::PARAM_STR);
				$sth3->execute();

			}else{

				$new_src = str_replace('src="uploads/','src="/wp-content/uploads/2020/11/',$src);
				
				if(file_exists(realpath(dirname(__FILE__)).$new_src)){
					
					$post_content = str_replace($src,$new_src,$post_content);
					
					$sth2->bindValue(':ID', $row['ID'], PDO::PARAM_INT);
					$sth2->bindValue(':post_content', $post_content, PDO::PARAM_STR);
					$sth2->execute();
				}
			}
		}
	}
}

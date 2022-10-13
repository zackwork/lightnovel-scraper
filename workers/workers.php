<?php

use Goutte\Client;

class LightNovel_Workers{
    public $lightnovelUrl;

    public $boxnovelThumbnailSelector = ".summary_image img";
    public $boxnovelTitleSelector = ".post-title > h1";
    public $boxnovelSummarySelector = ".description-summary .summary__content";
    public $boxnovelAuthorSelector = ".author-content a";
    public $boxnovelGenreSelector = ".genres-content a";
    public $boxnovelTagsSelector = ".tags-content a";
    public $boxnovelTypeSelector = "div.summary_content_wrap > div > div.post-content > div:nth-child(7) > div.summary-content";
    public $boxnovelStatusSelector = "div.summary_content_wrap > div > div.post-status > div.post-content_item > div.summary-content";
    


    public function __construct()
    {
        add_action('wp_ajax_lns_validate_lightnovel', array($this, 'lns_validate_lightnovel_post'));
        add_action('wp_ajax_lns_create_lightnovel', array($this, 'lns_create_lightnovel_post'));
        add_action('wp_ajax_lns_create_lightnovel_chapter', array($this, 'lns_create_lightnovel_chapter'));
    }

    public function lns_validate_lightnovel_post(){

        $this->lightnovelUrl = $_POST['lightnovelUrl'];

        $client = new Client();

        // Go to the Boxnovel website
        $crawler = $client->request('GET', $this->lightnovelUrl);
        // Get Chapters List
        $boxnovelchaptersCrawler = $client->request('POST', $this->lightnovelUrl . "ajax/chapters/");
        $boxnovelChapters = $boxnovelchaptersCrawler->filter('.listing-chapters_wrap li a')->each(function ($node) {
            $chapters = [
                "name" => $node->text(),
                "chapterlink" => $node->attr("href"),
                "extend_name" => ''
            ];
            return $chapters;
        });
        
        $boxnovelThumbnail = $crawler->filter($this->boxnovelThumbnailSelector)->attr("data-src");
        $boxnovelTitle = $crawler->filter($this->boxnovelTitleSelector)->text();
        $boxnovelSummary = $crawler->filter($this->boxnovelSummarySelector)->html('');
        $boxnovelAuthor = $crawler->filter($this->boxnovelAuthorSelector)->text('');
        $boxnovelGenre = $crawler->filter($this->boxnovelGenreSelector)->text('');
        $boxnovelTags = $crawler->filter($this->boxnovelTagsSelector)->text('');
        $boxnovelType = $crawler->filter($this->boxnovelTypeSelector)->text('');
        $boxnovelStatus = $crawler->filter($this->boxnovelStatusSelector)->text('onGoing');

        $boxnovelInfo = array(
            "title"       => $boxnovelTitle,
            "thumbnail"   => $boxnovelThumbnail,
            "summary"     => $boxnovelSummary,
            "author"      => $boxnovelAuthor,
            "genre"       => $boxnovelGenre,
            "tags"        => $boxnovelTags,
            "type"        => $boxnovelType,
            "status"      => $boxnovelStatus,
            "chapters"    => $boxnovelChapters
        );
        $post_id = post_exists($boxnovelTitle, '', '', 'wp-manga', 'publish');
        if ($post_id) {

            $result = [
                "validation" => true,
                "postId" => $post_id,
                "content" => array_reverse($boxnovelInfo['chapters'])
            ];
            echo json_encode($result);
            die();

        }else{

            $result = [
                "validation" => false,
                "content" => $boxnovelInfo
            ];
            echo json_encode($result);
            die();

        }
    }

    public function lns_create_lightnovel_post(){

        $lightnovelInfo = $_POST['content'];

        $my_post = array(
            'post_title'    => $lightnovelInfo['title'],
            'post_content'  => $lightnovelInfo['summary'],
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'wp-manga'
        );

        $post_id = wp_insert_post($my_post, true);


        // Add Featured Image to Post
        // Define the image URL here
        $image_name       = str_replace(" ", "-", $lightnovelInfo['title']) . ".jpg";
        $upload_dir       = wp_upload_dir(); // upload folder
        $image_data       = file_get_contents($lightnovelInfo['thumbnail']); // Get image data
        $unique_file_name = wp_unique_filename($upload_dir['path'], $image_name); // Generate unique name
        $filename         = basename($unique_file_name); // Create image file name

        // Check folder permission and define file location
        if (wp_mkdir_p($upload_dir['path'])) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }

        // Create the image  file on the server
        file_put_contents($file, $image_data);

        // Check image file type
        $wp_filetype = wp_check_filetype($filename, null);

        // Set attachment data
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => sanitize_file_name($filename),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        // Create the attachment
        $attach_id = wp_insert_attachment($attachment, $file, $post_id);

        // Include image.php
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Define attachment metadata
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);

        // Assign metadata to attachment
        wp_update_attachment_metadata($attach_id, $attach_data);

        // And finally assign featured image to post
        set_post_thumbnail($post_id, $attach_id);

        $lnStatus = "";
        if($lightnovelInfo['status'] == "OnGoing"){
            $lnStatus = "on-going";
        }else{
            $lnStatus = "end";
        }

        $meta_data = array(
            '_thumbnail_id'          => $attach_id,
            '_wp_manga_alternative'  => "",
            '_wp_manga_type'         => $lightnovelInfo['type'],
            '_wp_manga_status'       => $lnStatus,
            '_wp_manga_chapter_type' => 'text',
        );

        foreach ($meta_data as $key => $value) {
            if (!empty($value)) {
                update_post_meta($post_id, $key, $value);
            }
        }

        //3.update terms
        $manga_terms = array(
            'wp-manga-release'     => "",
            'wp-manga-author'      =>  $lightnovelInfo['author'],
            'wp-manga-artist'      =>  "",
            'wp-manga-genre'       => $lightnovelInfo['genre'],
            'wp-manga-tag'         => $lightnovelInfo['tags'],
        );


        foreach ($manga_terms as $taxonomy => $term) {

            $terms = explode(',', $term);

            if (empty($terms)) {
                return false;
            }

            $taxonomy_obj = get_taxonomy($taxonomy);

            if ($taxonomy_obj->hierarchical) {

                $output_terms = array();

                foreach ($terms as $current_term) {

                    if (empty($current_term)) {
                        continue;
                    }

                    //check if term is exist
                    $term = term_exists($current_term, $taxonomy);

                    //then add if it isn't
                    if (!$term || is_wp_error($term)) {
                        $term = wp_insert_term($current_term, $taxonomy);
                        if (!is_wp_error($term) && isset($term['term_id'])) {
                            $term = intval($term['term_id']);
                        } else {
                            continue;
                        }
                    } else {
                        $term = intval($term['term_id']);
                    }

                    $output_terms[] = $term;
                }

                $terms = $output_terms;
            }
            if(is_array($lightnovelInfo['genre'])){
                if ($taxonomy == 'wp-manga-genre' && !empty([implode(', ', $lightnovelInfo['genre'])]['genres'])) {
                    $terms = array_merge($terms, [implode(', ', $lightnovelInfo['genre'])]);
                }
            }else{
                
                if ($taxonomy == 'wp-manga-genre' && !empty([$lightnovelInfo['genre']]['genres'])) {
                    $terms = array_merge($terms, [$lightnovelInfo['genre']]);
                }
            }

            wp_set_post_terms($post_id, $terms, $taxonomy);
        }



        
        $result = [
            "success" => true,
            "message" => "successfuly inserted post",
            "postId" => $post_id,
            "results" => array_reverse($lightnovelInfo['chapters'])
        ];
        echo json_encode($result);
        die();

    }


    public function lns_create_lightnovel_chapter(){
        global $wp_manga, $wp_manga_storage, $wp_manga_chapter, $wp_manga_text_type;

        $chapter = $_POST['content'];
        $post_id = $_POST['postid'];
        
        
        if (strpos($chapter['name'], "-") !== false) {
            $chapeq = explode("-", $chapter['name']);
            $chapterName = $chapeq[0];
            $chapterExtended = $chapeq[1]; 
        }else{
            $chapterName = $chapter['name'];
            $chapterExtended = ""; 
        }
        

        $uniqid = $wp_manga->get_uniqid($post_id);
        $postUrl = get_permalink($post_id);
        $slugified_name = $wp_manga_storage->slugify($chapterName);

        // check if chapter exists to prevent duplication
       
        $chapter_2 = $wp_manga_chapter->get_chapter_by_slug($post_id, $slugified_name);
        if ($chapter_2 && $chapter_2['chapter_name'] == $chapterName) {
            return true;
        }

        if ($chapter_2 == true) {
            
            $result = [
                "validation" => false,
                "url"        => $postUrl
            ];

            echo json_encode($result);
            die();
        }


        $client = new Client();

        // Go to the chapter link
        $crawler = $client->request('GET', $chapter['chapterlink']);

        $chapterContent = $crawler->filter(".reading-content .text-left")->html('');

        

        // Create Chapter
        $chapter_args = array(
            'post_id'             => $post_id,
            'volume_id'           => 0,
            'chapter_name'        => $chapterName,
            'chapter_name_extend' => $chapterExtended,
            'chapter_content'        => $chapterContent,
        );
        
        $chapter_id = $wp_manga_text_type->insert_chapter( $chapter_args );

        
        
        $result = [
            "validation" => true,
            "url" =>  $postUrl
        ];


        echo json_encode($result);
        die();

    }
}

$LightNovel_Workers = new LightNovel_Workers();

<?php
if ( !class_exists( 'SimpleAdsManager' ) ) {
  class SimpleAdsManager {
    protected $samOptions = array();
    private $samVersions = array('sam' => null, 'db' => null);
    private $crawler = false;
    public $samNonce;
    private $whereClauses;
    
    private $defaultSettings = array(
      'adCycle' => 1000,
      'adShow' => 'php', // php|js
      'adDisplay' => 'blank',
      'placesPerPage' => 10,
      'itemsPerPage' => 10,
	    'deleteOptions' => 0,
      'deleteDB' => 0,
      'deleteFolder' => 0,
      'beforePost' => 0,
      'bpAdsId' => 0,
      'bpUseCodes' => 0,
      'bpExcerpt' => 0,
      'bbpBeforePost' => 0,
      'bbpList' => 0,
      'middlePost' => 0,
      'mpAdsId' => 0,
      'mpUseCodes' => 0,
      'bbpMiddlePost' => 0,
      'afterPost' => 0,
      'apAdsId' => 0,
      'apUseCodes' => 0,
      'bbpAfterPost' => 0,
      'useDFP' => 0,
      'detectBots' => 0,
      'detectingMode' => 'inexact',
      'currency' => 'auto',
      'dfpPub' => '',
      'dfpBlocks' => array(),
      'editorButtonMode' => 'modern', // modern|classic
      'useSWF' => 0,
      'access' => 'manage_options',
      'errorlog' => 1,
      'errorlogFS' => 1,
      'bbpActive' => 0,
      'bbpEnabled' => 0,
      // Mailer
      'mailer' => 1,
      'mail_subject' => 'Ad campaign report ([month])',
      'mail_greeting' => 'Hi! [name]!',
      'mail_text_before' => 'This is your Ad Campaing Report:',
      'mail_text_after' => '',
      'mail_warning' => 'You received this mail because you are an advertiser of site [site]. If time of your campaign expires or if you refuse to post your ads on our site, you will be excluded from the mailing list automatically. Thank you for your cooperation.',
      'mail_message' => 'Do not respond to this mail! This mail was sent automatically by Wordpress plugin Simple Ads Manager.'
	  );
		
	  public function __construct() {
      define('SAM_VERSION', '2.0.74');
      define('SAM_DB_VERSION', '2.6');
      define('SAM_PATH', dirname( __FILE__ ));
      define('SAM_URL', plugins_url( '/',  __FILE__  ) );
      define('SAM_IMG_URL', SAM_URL.'images/');
      define('SAM_DOMAIN', 'simple-ads-manager');
      define('SAM_OPTIONS_NAME', 'samPluginOptions');
      define('SAM_AD_IMG', WP_PLUGIN_DIR.'/sam-images/');
      define('SAM_AD_URL', plugins_url('/sam-images/'));
      
      define('SAM_IS_HOME', 1);
      define('SAM_IS_SINGULAR', 2);
      define('SAM_IS_SINGLE', 4);
      define('SAM_IS_PAGE', 8);
      define('SAM_IS_ATTACHMENT', 16);
      define('SAM_IS_SEARCH', 32);
      define('SAM_IS_404', 64);
      define('SAM_IS_ARCHIVE', 128);
      define('SAM_IS_TAX', 256);
      define('SAM_IS_CATEGORY', 512);
      define('SAM_IS_TAG', 1024);
      define('SAM_IS_AUTHOR', 2048);
      define('SAM_IS_DATE', 4096);
      define('SAM_IS_POST_TYPE', 8192);
      define('SAM_IS_POST_TYPE_ARCHIVE', 16384);

      $this->getSettings(true);
      $this->getVersions(true);
      $this->crawler = $this->isCrawler();

      add_action('plugins_loaded', array(&$this, 'samMaintenance'));

      if(!is_admin()) {
        add_action('wp_enqueue_scripts', array(&$this, 'headerScripts'));
        add_action('wp_head', array(&$this, 'headerCodes'));
        
        add_shortcode('sam', array(&$this, 'doShortcode'));
        add_shortcode('sam_ad', array(&$this, 'doAdShortcode'));
        add_shortcode('sam_zone', array(&$this, 'doZoneShortcode'));
        add_shortcode('sam_block', array(&$this, 'doBlockShortcode'));      
        add_filter('the_content', array(&$this, 'addContentAds'), 8);
        add_filter('get_the_excerpt', array(&$this, 'addExcerptAds'), 10);
        if( $this->samOptions['bbpActive'] && $this->samOptions['bbpEnabled'] ) {
          add_filter('bbp_get_reply_content', array(&$this, 'addBbpContentAds'), 39, 2);
          add_filter('bbp_get_topic_content', array(&$this, 'addBbpContentAds'), 39, 2);
          add_action('bbp_theme_after_forum_sub_forums', array(&$this, 'addBbpForumAds'));
          add_action('bbp_theme_before_topic_started_by', array(&$this, 'addBbpForumAds'));
        }

        // For backward compatibility
        add_shortcode('sam-ad', array(&$this, 'doAdShortcode'));
        add_shortcode('sam-zone', array(&$this, 'doZoneShortcode'));
      }
      else $this->whereClauses = null;
    }
		
	  public function getSettings($force = false) {
	    if($force) {
        $pluginOptions = get_option(SAM_OPTIONS_NAME, '');
		    $options = $this->defaultSettings;
		    if ($pluginOptions !== '') {
		      foreach($pluginOptions as $key => $option) {
			      $options[$key] = $option;
		      }
		    }
		    $this->samOptions = $options;
      }
      else $options = $this->samOptions;
      return $options; 
	  }
    
    public function getVersions($force = false) {
      $versions = array('sam' => null, 'db' => null);
      if($force) {
        $versions['sam'] = get_option( 'sam_version', '' );
        $versions['db'] = get_option( 'sam_db_version', '' );
        $this->samVersions = $versions;
      }
      else $versions = $this->samVersions;
      
      return $versions;
    }

    private function getCustomPostTypes() {
      $args = array('public' => true, '_builtin' => false);
      $output = 'names';
      $operator = 'and';
      $post_types = get_post_types($args, $output, $operator);

      return $post_types;
    }

    private function isCustomPostType() {
      return (in_array(get_post_type(), $this->getCustomPostTypes()));
    }

    private function customTaxonomiesTerms($id) {
      $post = get_post($id);
      $postType = $post->post_type;
      $taxonomies = get_object_taxonomies($postType, 'objects');

      $out = array();
      foreach ($taxonomies as $tax_slug => $taxonomy) {
        $terms = get_the_terms($id, $tax_slug);
        if(!empty($terms)) {
          foreach($terms as $term) {
            $out[] = $term->slug;
          }
        }
      }
      return $out;
    }

    public function samMaintenance() {
      $options = self::getSettings();
      if(false === ($mDate = get_transient( 'sam_maintenance_date' ))) {
        $date = new DateTime('now');
        $date->modify('+1 month');
        $nextDate = new DateTime($date->format('Y-m-01 02:00'));
        $diff = $nextDate->format('U') - $_SERVER['REQUEST_TIME'];

        if($options['mailer']) {
          include_once('sam.tools.php');
          $mailer = new SamMailer($options);
          $mailer->sendMails();
        }

        $format = get_option('date_format').' '.get_option('time_format');
        set_transient( 'sam_maintenance_date', $nextDate->format($format), $diff );
      }
    }

    private function customTaxonomiesTerms2($id) {
      global $wpdb;

      $tTable = $wpdb->prefix . "terms";
      $ttTable = $wpdb->prefix . "term_taxonomy";
      $trTable = $wpdb->prefix . 'term_relationships';

      $sql = "SELECT wt.slug
              FROM $trTable wtr
                INNER JOIN $ttTable wtt
                  ON wtr.term_taxonomy_id = wtt.term_taxonomy_id
                INNER JOIN $tTable wt
                  ON wtt.term_id = wt.term_id
              WHERE NOT FIND_IN_SET(wtt.taxonomy, 'category,post_tag,nav_menu,link_category,post_format') AND wtr.object_id = %d";

      $cTax = $wpdb->get_results($wpdb->prepare( $sql, $id ), ARRAY_A);
      return $cTax;
    }

    public function buildWhereClause() {
      $settings = $this->getSettings();
      if($settings['adCycle'] == 0) $cycle = 1000;
      else $cycle = $settings['adCycle'];

      global $current_user;

      $viewPages = 0;
      $wcc = '';
      $wci = '';
      $wca = '';
      $wcx = '';
      $wct = '';
      $wcxc = '';
      $wcxa = '';
      $wcxt = '';
      $wcct = '';
      $wcxct = '';

      if(is_user_logged_in()) {
        get_currentuserinfo();
        $uSlug = $current_user->user_login;
        $wcul = "IF(sa.ad_users_reg = 1, IF(sa.x_ad_users = 1, NOT FIND_IN_SET(\"$uSlug\", sa.x_view_users), TRUE) AND IF(sa.ad_users_adv = 1, (sa.adv_nick <> \"$uSlug\"), TRUE), FALSE)";
      }
      else {
        $wcul = "(sa.ad_users_unreg = 1)";
      }
      $wcu = "(IF(sa.ad_users = 0, TRUE, $wcul)) AND";

      if(is_home() || is_front_page()) $viewPages += SAM_IS_HOME;
      if(is_singular()) {
        $viewPages |= SAM_IS_SINGULAR;
        if($this->isCustomPostType()) {
          $viewPages |= SAM_IS_SINGLE;
          $viewPages |= SAM_IS_POST_TYPE;

          $postType = get_post_type();
          $wct .= " AND IF(sa.view_type < 2 AND sa.ad_custom AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), FIND_IN_SET(\"$postType\", sa.view_custom), TRUE)";
          $wcxt .= " AND IF(sa.view_type < 2 AND sa.x_custom AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), NOT FIND_IN_SET(\"$postType\", sa.x_view_custom), TRUE)";
        }
        if(is_single()) {
          global $post;

          $viewPages |= SAM_IS_SINGLE;
          $categories = get_the_category($post->ID);
          $tags = get_the_tags();
          $postID = ((!empty($post->ID)) ? $post->ID : 0);
          $customTerms = self::customTaxonomiesTerms($postID);

          if(!empty($categories)) {
            $wcc_0 = '';
            $wcxc_0 = '';
            $wcc = " AND IF(sa.view_type < 2 AND sa.ad_cats AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE),";
            $wcxc = " AND IF(sa.view_type < 2 AND sa.x_cats AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE),";
            foreach($categories as $category) {
              if(empty($wcc_0)) $wcc_0 = " FIND_IN_SET(\"{$category->category_nicename}\", sa.view_cats)";
              else $wcc_0 .= " OR FIND_IN_SET(\"{$category->category_nicename}\", sa.view_cats)";
              if(empty($wcxc_0)) $wcxc_0 = " (NOT FIND_IN_SET(\"{$category->category_nicename}\", sa.x_view_cats))";
              else $wcxc_0 .= " AND (NOT FIND_IN_SET(\"{$category->category_nicename}\", sa.x_view_cats))";
            }
            $wcc .= $wcc_0.", TRUE)";
            $wcxc .= $wcxc_0.", TRUE)";
          }

          if(!empty($tags)) {
            $wct_0 = '';
            $wcxt_0 = '';
            $wct .= " AND IF(sa.view_type < 2 AND sa.ad_tags AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE),";
            $wcxt .= " AND IF(sa.view_type < 2 AND sa.x_tags AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE),";
            foreach($tags as $tag) {
              if(empty($wct_0)) $wct_0 = " FIND_IN_SET(\"{$tag->slug}\", sa.view_tags)";
              else $wct_0 .= " OR FIND_IN_SET(\"{$tag->slug}\", sa.view_tags)";
              if(empty($wcxt_0)) $wcxt_0 = " (NOT FIND_IN_SET(\"{$tag->slug}\", sa.x_view_tags))";
              else $wcxt_0 .= " AND (NOT FIND_IN_SET(\"{$tag->slug}\", sa.x_view_tags))";
            }
            $wct .= $wct_0.", TRUE)";
            $wcxt .= $wcxt_0.", TRUE)";
          }

          if(!empty($customTerms)) {
            $wcct_0 = '';
            $wcxct_0 = '';
            $wcct .= " AND IF(sa.view_type < 2 AND sa.ad_custom_tax_terms AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE),";
            $wcxct .= " AND IF(sa.view_type < 2 AND sa.x_ad_custom_tax_terms AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE),";
            foreach($customTerms as $cTerm) {
              if(empty($wcct_0)) $wcct_0 = " FIND_IN_SET(\"{$cTerm}\", sa.view_custom_tax_terms)";
              else $wcct_0 .= " OR FIND_IN_SET(\"{$cTerm}\", sa.view_custom_tax_terms)";
              if(empty($wcxct_0)) $wcxct_0 = " (NOT FIND_IN_SET(\"{$cTerm}\", sa.x_view_custom_tax_terms))";
              else $wcxct_0 .= " AND (NOT FIND_IN_SET(\"{$cTerm}\", sa.x_view_custom_tax_terms))";
            }
            $wcct .= $wcct_0 . ", TRUE)";
            $wcxct .= $wcxct_0 . ", TRUE)";
          }

          $wci = " OR (sa.view_type = 2 AND FIND_IN_SET({$postID}, sa.view_id))";
          $wcx = " AND IF(sa.x_id, NOT FIND_IN_SET({$postID}, sa.x_view_id), TRUE)";
          $author = get_userdata($post->post_author);
          $wca = " AND IF(sa.view_type < 2 AND sa.ad_authors AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), FIND_IN_SET(\"{$author->user_login}\", sa.view_authors), TRUE)";
          $wcxa = " AND IF(sa.view_type < 2 AND sa.x_authors AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), NOT FIND_IN_SET(\"{$author->user_login}\", sa.x_view_authors), TRUE)";
        }
        if(is_page()) {
          global $post;
          $postID = ((!empty($post->ID)) ? $post->ID : 0);

          $viewPages |= SAM_IS_PAGE;
          $wci = " OR (sa.view_type = 2 AND FIND_IN_SET({$postID}, sa.view_id))";
          $wcx = " AND IF(sa.x_id, NOT FIND_IN_SET({$postID}, sa.x_view_id), TRUE)";
        }
        if(is_attachment()) $viewPages |= SAM_IS_ATTACHMENT;
      }
      if(is_search()) $viewPages |= SAM_IS_SEARCH;
      if(is_404()) $viewPages |= SAM_IS_404;
      if(is_archive()) {
        $viewPages |= SAM_IS_ARCHIVE;
        if(is_tax()) {
          $viewPages |= SAM_IS_TAX;
          $term = get_query_var('term');
          $wcct = " AND IF(sa.view_type < 2 AND sa.ad_custom_tax_terms AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), FIND_IN_SET('{$term}', sa.view_custom_tax_terms), TRUE)";
          $wcxct = " AND IF(sa.view_type < 2 AND sa.x_ad_custom_tax_terms AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), NOT FIND_IN_SET('{$term}', sa.x_view_custom_tax_terms), TRUE)";
        }
        if(is_category()) {
          $viewPages |= SAM_IS_CATEGORY;
          $cat = get_category(get_query_var('cat'), false);
          $wcc = " AND IF(sa.view_type < 2 AND sa.ad_cats AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), FIND_IN_SET(\"{$cat->category_nicename}\", sa.view_cats), TRUE)";
          $wcxc = " AND IF(sa.view_type < 2 AND sa.x_cats AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), NOT FIND_IN_SET(\"{$cat->category_nicename}\", sa.x_view_cats), TRUE)";
        }
        if(is_tag()) {
          $viewPages |= SAM_IS_TAG;
          $tag = get_tag(get_query_var('tag_id'));
          $wct = " AND IF(sa.view_type < 2 AND sa.ad_tags AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), FIND_IN_SET('{$tag->slug}', sa.view_tags), TRUE)";
          $wcxt = " AND IF(sa.view_type < 2 AND sa.x_tags AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), NOT FIND_IN_SET('{$tag->slug}', sa.x_view_tags), TRUE)";
        }
        if(is_author()) {
          global $wp_query;

          $viewPages |= SAM_IS_AUTHOR;
          $author = $wp_query->get_queried_object();
          $wca = " AND IF(sa.view_type < 2 AND sa.ad_authors = 1 AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), FIND_IN_SET('{$author->user_login}', sa.view_authors), TRUE)";
          $wcxa = " AND IF(sa.view_type < 2 AND sa.x_authors AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), NOT FIND_IN_SET('{$author->user_login}', sa.x_view_authors), TRUE)";
        }
        if(is_post_type_archive()) {
          $viewPages |= SAM_IS_POST_TYPE_ARCHIVE;
          //$postType = post_type_archive_title( '', false );
          $postType = get_post_type();
          $wct = " AND IF(sa.view_type < 2 AND sa.ad_custom AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), FIND_IN_SET('{$postType}', sa.view_custom), TRUE)";
          $wcxt = " AND IF(sa.view_type < 2 AND sa.x_custom AND IF(sa.view_type = 0, sa.view_pages+0 & $viewPages, TRUE), NOT FIND_IN_SET('{$postType}', sa.x_view_custom), TRUE)";
        }
        if(is_date()) $viewPages |= SAM_IS_DATE;
      }

      if(empty($wcc)) $wcc = " AND (sa.ad_cats = 0)";
      if(empty($wca)) $wca = " AND (sa.ad_authors = 0)";

      $whereClause  = "$wcu ((sa.view_type = 1)";
      $whereClause .= " OR (sa.view_type = 0 AND (sa.view_pages+0 & $viewPages))";
      $whereClause .= "$wci)";
      $whereClause .= "$wcc $wca $wct $wcct $wcx $wcxc $wcxa $wcxt $wcxct";
      $whereClauseT = " AND IF(sa.ad_schedule, CURDATE() BETWEEN sa.ad_start_date AND sa.ad_end_date, TRUE)";
      $whereClauseT .= " AND IF(sa.limit_hits, sa.hits_limit > sa.ad_hits, TRUE)";
      $whereClauseT .= " AND IF(sa.limit_clicks, sa.clicks_limit > sa.ad_clicks, TRUE)";

      $whereClauseW = " AND IF(sa.ad_weight > 0, (sa.ad_weight_hits*10/(sa.ad_weight*$cycle)) < 1, FALSE)";
      $whereClause2W = "AND (sa.ad_weight > 0)";

      return array('WC' => $whereClause, 'WCT' => $whereClauseT, 'WCW' => $whereClauseW, 'WC2W' => $whereClause2W);
    }
    
    public function headerScripts() {
      global $SAM_Query;

      $this->samNonce = wp_create_nonce('samNonce');
      $options = self::getSettings();
      if(empty($this->whereClauses)) $this->whereClauses = self::buildWhereClause();

      $SAM_Query = array('clauses' => $this->whereClauses);
      $clauses64 = base64_encode(serialize($SAM_Query['clauses']));
      
      wp_enqueue_script('jquery');
      if($options['useSWF']) wp_enqueue_script('swfobject');
      wp_enqueue_script('samLayout', SAM_URL.'js/sam-layout.min.js', array('jquery'), SAM_VERSION);
      wp_localize_script('samLayout', 'samAjax', array(
          'ajaxurl' => SAM_URL . 'sam-ajax.php',
          'loadurl' => SAM_URL . 'sam-ajax-loader.php',
          'load' => ($this->samOptions['adShow'] == 'js'),
          'level' => count(explode('/', str_replace( ABSPATH, '', dirname( __FILE__ ) ))),
          'clauses' => $clauses64
        )
      );
    }
    
    public function headerCodes() {
      $options = $this->getSettings();
      $pub = $options['dfpPub'];
      
      if(($options['useDFP'] == 1) && !empty($options['dfpPub'])) {
        $output = "<!-- Start of SAM ".SAM_VERSION." scripts -->"."\n";
        $output .= "<script type='text/javascript' src='http://partner.googleadservices.com/gampad/google_service.js'></script>"."\n";
        $output .= "<script type='text/javascript'>"."\n";
        $output .= "  GS_googleAddAdSenseService('$pub');"."\n";
        $output .= "  GS_googleEnableAllServices();"."\n";
        $output .= "</script>"."\n";
        $output .= "<script type='text/javascript'>"."\n";
        foreach($options['dfpBlocks'] as $value)
          $output .= "  GA_googleAddSlot('$pub', '$value');"."\n";
        $output .= "</script>"."\n";
        $output .= "<script type='text/javascript'>"."\n";
        $output .= "  GA_googleFetchAds();"."\n";
        $output .= "</script>"."\n";
        $output .= "<!-- End of SAM ".SAM_VERSION." scripts -->"."\n";
      }
      else $output = '';
      
      echo $output;
    }
    
    private function isCrawler() {
      $options = $this->getSettings();
      $crawler = false;
      
      if($options['detectBots'] == 1) {
        switch($options['detectingMode']) {
          case 'inexact':
            if($_SERVER["HTTP_USER_AGENT"] == '' ||
               $_SERVER['HTTP_ACCEPT'] == '' ||
               $_SERVER['HTTP_ACCEPT_ENCODING'] == '' ||
               $_SERVER['HTTP_ACCEPT_LANGUAGE'] == '' ||
               $_SERVER['HTTP_CONNECTION']=='') $crawler == true;
            break;
            
          case 'exact':
            if(!class_exists('samBrowser')) include_once('sam-browser.php');
            $browser = new samBrowser();
            $crawler = $browser->isRobot();
            break;
            
          case 'more':
            if(ini_get("browscap")) {
              $browser = get_browser(null, true);
              $crawler = $browser['crawler']; 
            }
            break;
        }
      }
      return $crawler;
    }
		
		/**
    * Outputs the Single Ad.
    *
    * Returns Single Ad content.
    *
    * @since 0.5.20
    *
    * @param array $args 'id' array element: id of ad, 'name' array elemnt: name of ad
    * @param bool|array $useCodes If bool codes 'before' and 'after' from Ads Place record are used. If array codes 'before' and 'after' from array are used
    * @return string value of Ad content
    */
    public function buildSingleAd( $args = null, $useCodes = false ) {
      $ad = new SamAd($args, $useCodes, $this->crawler);
      $output = $ad->ad;
      return $output;
    }
    
    /**
    * Outputs Ads Place content.
    *
    * Returns Ads Place content.
    *
    * @since 0.1.1
    *
    * @param array $args 'id' array element: id of Ads Place, 'name' array elemnt: name of Ads Place
    * @param bool|array $useCodes If bool codes 'before' and 'after' from Ads Place record are used. If array codes 'before' and 'after' from array are used
    * @return string value of Ads Place content
    */
    public function buildAd( $args = null, $useCodes = false ) {
      $ad = new SamAdPlace($args, $useCodes, $this->crawler);
      $output = $ad->ad;
      return $output;
    }
    
    /**
    * Outputs Ads Zone content.
    *
    * Returns Ads Zone content.
    *
    * @since 0.5.20
    *
    * @param array $args 'id' array element: id of Ads Zone, 'name' array elemnt: name of Ads Zone
    * @param bool|array $useCodes If bool codes 'before' and 'after' from Ads Place record are used. If array codes 'before' and 'after' from array are used
    * @return string value of Ads Zone content
    */
    public function buildAdZone( $args = null, $useCodes = false ) {
      $ad = new SamAdPlaceZone($args, $useCodes, $this->crawler);
      $output = $ad->ad;
      return $output;
    }
    
    /**
    * Outputs Ads Block content.
    *
    * Returns Ads Block content.
    *
    * @since 1.0.25
    *
    * @param array $args 'id' array element: id of Ads Block, 'name' array elemnt: name of Ads Block
    * @return string value of Ads Zone content
    */
    public function buildAdBlock( $args = null ) {
      $block = new SamAdBlock($args, $this->crawler);
      $output = $block->ad;
      return $output;
    }
    
    public function doAdShortcode($atts) {
      shortcode_atts( array( 'id' => '', 'name' => '', 'codes' => ''), $atts );
      $ad = new SamAd(array('id' => $atts['id'], 'name' => $atts['name']), ($atts['codes'] == 'true'), $this->crawler);
      return $ad->ad;
    }
    
    public function doShortcode( $atts ) {
      shortcode_atts( array( 'id' => '', 'name' => '', 'codes' => ''), $atts );
      $ad = new SamAdPlace(array('id' => $atts['id'], 'name' => $atts['name']), ($atts['codes'] == 'true'), $this->crawler);
      return $ad->ad;
    }
    
    public function doZoneShortcode($atts) {
      shortcode_atts( array( 'id' => '', 'name' => '', 'codes' => ''), $atts );
      $ad = new SamAdPlaceZone(array('id' => $atts['id'], 'name' => $atts['name']), ($atts['codes'] == 'true'), $this->crawler);
      return $ad->ad;
    }
    
    public function doBlockShortcode($atts) {
      shortcode_atts( array( 'id' => '', 'name' => ''), $atts );
      $block = new SamAdBlock(array('id' => $atts['id'], 'name' => $atts['name']), $this->crawler);
      return $block->ad;
    }
    
    public function addContentAds( $content ) {
      $options = self::getSettings();
      $bpAd = '';
      $apAd = '';
      $mpAd = '';
      
      if(is_single() || is_page()) {
        if(!empty($options['beforePost']) && !empty($options['bpAdsId'])) 
          $bpAd = $this->buildAd(array('id' => $options['bpAdsId']), $options['bpUseCodes']);
        if(!empty($options['middlePost']) && !empty($options['mpAdsId']))
          $mpAd = $this->buildAd(array('id' => $options['mpAdsId']), $options['mpUseCodes']);
        if(!empty($options['afterPost']) && !empty($options['apAdsId'])) 
          $apAd = $this->buildAd(array('id' => $options['apAdsId']), $options['apUseCodes']);
      }
      elseif($options['bpExcerpt']) {
        if(!empty($options['beforePost']) && !empty($options['bpAdsId']))
          $bpAd = $this->buildAd(array('id' => $options['bpAdsId']), $options['bpUseCodes']);
      }

      if(!empty($mpAd)) {
        $xc = explode("\r\n", $content);
        $hm = ceil(count($xc)/2);
        $cntFirst = implode("\r\n", array_slice($xc, 0, $hm));
        $cntLast = implode("\r\n", array_slice($xc, $hm));

        return $bpAd.$cntFirst.$mpAd.$cntLast.$apAd;
      }
      else return $bpAd.$content.$apAd;
    }

    public function addExcerptAds( $excerpt ) {
      $options = self::getSettings();
      $bpAd = '';
      if(!is_single()) {
        if(empty($this->whereClauses)) $this->whereClauses = self::buildWhereClause();

        if(!empty($options['beforePost']) && !empty($options['bpExcerpt']) && !empty($options['bpAdsId'])) {
          $oBpAd = new SamAdPlace(array('id' => $options['bpAdsId']), $options['bpUseCodes'], false, $this->whereClauses);
          $bpAd = $oBpAd->ad;
        }

        return $bpAd.$excerpt;
      }
      else return $excerpt;
    }

    public function addBbpContentAds( $content, $reply_id ) {
      $options = self::getSettings();
      $bpAd = '';
      $apAd = '';
      $mpAd = '';
      if(empty($this->whereClauses)) $this->whereClauses = self::buildWhereClause();

      if(!empty($options['bbpBeforePost']) && !empty($options['bpAdsId'])) {
        $oBpAd = new SamAdPlace(array('id' => $options['bpAdsId']), $options['bpUseCodes'], false, $this->whereClauses);
        $bpAd = $oBpAd->ad;
      }
      if(!empty($options['bbpMiddlePost']) && !empty($options['mpAdsId'])) {
        $oMpAd = new SamAdPlace(array('id' => $options['mpAdsId']), $options['mpUseCodes'], false, $this->whereClauses);
        $mpAd = $oMpAd->ad;
      }
      if(!empty($options['bbpAfterPost']) && !empty($options['apAdsId'])) {
        $oApAd = new SamAdPlace(array('id' => $options['apAdsId']), $options['apUseCodes'], false, $this->whereClauses);
        $apAd = $oApAd->ad;
      }

      if(!empty($mpAd)) {
        $xc = explode("\r\n", $content);
        if(count($xc) < 3) return $bpAd.$content.$apAd;
        else {
          $hm = ceil(count($xc)/2);
          $cntFirst = implode("\r\n", array_slice($xc, 0, $hm));
          $cntLast = implode("\r\n", array_slice($xc, $hm));

          return $bpAd.$cntFirst.$mpAd.$cntLast.$apAd;
        }
      }
      else return $bpAd.$content.$apAd;
    }

    public function addBbpForumAds() {
      $options = self::getSettings();
      $bpAd = '';
      if(empty($this->whereClauses)) $this->whereClauses = self::buildWhereClause();

      if(!empty($options['bbpList']) && !empty($options['bpAdsId'])) {
        $oBpAd = new SamAdPlace(array('id' => $options['bpAdsId']), $options['bpUseCodes'], false, $this->whereClauses);
        $bpAd = $oBpAd->ad;
      }

      echo $bpAd;
    }
  } // end of class definition
} // end of if not class SimpleAdsManager exists
?>
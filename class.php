<?php
/**
 * Wpautop_mask Class
 */
Class Wpautop_mask{
	const OPTION_NAME = "wpautop_mask_shortcodes";
	const DEFAULT_TAG = "wpautop_mask";
	const DEFAULT_TAG_H = "wpautop_mask_h";

	public static function init(){
		add_action("admin_menu", array(__CLASS__, "add_menu"));
		add_filter("the_content", array(__CLASS__, "main_filter"), 9);
		add_filter("the_content", array(__CLASS__, "remove_html_comments"), 12);
		add_shortcode(self::DEFAULT_TAG, array(__CLASS__, "default_shortcode"));
		add_shortcode(self::DEFAULT_TAG_H, array(__CLASS__, "default_shortcode_h"));
		add_filter("no_texturize_shortcodes", array(__CLASS__, "no_texturize_shortcodes"));
	}

	public static function main_filter($content = ""){
		$shortcodes = get_option(self::OPTION_NAME);
		if( empty($shortcodes) || !is_array($shortcodes) ) $shortcodes = array();
		if( !in_array(self::DEFAULT_TAG, $shortcodes, true) ) $shortcodes[] = self::DEFAULT_TAG;
		if( !in_array(self::DEFAULT_TAG_H, $shortcodes, true) ) $shortcodes[] = self::DEFAULT_TAG_H;

		$shortcodes_exists = false;
		foreach($shortcodes as $c){
			if( mb_strpos($content, "[{$c}]") !== false && mb_strpos($content, "[/{$c}]") !== false ) $shortcodes_exists = true;
		}
		if( !$shortcodes_exists ) return $content;
		remove_filter("the_content", "wpautop");

		$br = 0;
		while( true ){
			$pos = array();
			foreach($shortcodes as $c){
				if( mb_strpos($content, "[{$c}]") !== false && mb_strpos($content, "[/{$c}]") !== false ){
					$pos[$c] = mb_strpos($content, "[{$c}]");
				}
			}
			if( empty($pos) ) break;
			asort($pos);
			reset($pos);
			$c = key($pos);

			$slice_start = mb_strpos($content, "[{$c}]");
			$slice_end = mb_strpos($content, "[/{$c}]");
			while( $slice_start > $slice_end ){
				$slice_end = mb_strpos($content, "[/{$c}]", $slice_end + 1);
				if( $slice_end === false ) break 2;
			}
			$autop[] = mb_substr($content, 0, $slice_start);
			$noautop[] = mb_substr($content, $slice_start, $slice_end + mb_strlen("[/{$c}]") - $slice_start);
			$content = mb_substr($content, $slice_end + mb_strlen("[/{$c}]"));
			if( $br++ > 999 ) break;
		}

		$result = "";
		if( isset($autop) && is_array($autop) ){
			for($i = 0; $i < count($autop); $i++){
				$noautop[$i] = str_replace("<", "<<!-- wpautop-mask -->", $noautop[$i]);
				$result .= wpautop($autop[$i]).$noautop[$i];
			}
		}
		return $result.wpautop($content);
	}

	public static function remove_html_comments($content = ""){
		return str_replace("<!-- wpautop-mask -->", "", $content);
	}

	public static function default_shortcode($atts, $content = ""){
		if( empty($content) ) return "";
		return do_shortcode($content);
	}

	public static function default_shortcode_h($atts, $content = ""){
		if( empty($content) ) return "";
		$content = str_replace("<!-- wpautop-mask -->", "", $content);
		$content = htmlspecialchars(do_shortcode($content));
		return "<pre class=\"wpautop-mask-h-code\"><code>{$content}</code></pre>";
	}

	public static function no_texturize_shortcodes($shortcodes = array()){
		$shortcodes[] = self::DEFAULT_TAG_H;
		return $shortcodes;
	}

	public static function add_menu(){
		add_options_page("Wpautop Mask", "Wpautop Mask", "manage_options", "wpautop_mask_settings", array(__CLASS__, "menu_html"));
	}

	public static function menu_html(){
		$shortcodes = get_option(self::OPTION_NAME);
		if( empty($shortcodes) || !is_array($shortcodes) ) $shortcodes = array();

		$info = "";
		if( !empty($_POST["nonce"]) && wp_verify_nonce($_POST["nonce"], plugin_basename(__FILE__)) ){
			$posted_value = ( !empty($_POST["shortcodes"]) && is_string($_POST["shortcodes"]) ) ? $_POST["shortcodes"] : "";
			$posted_value = str_replace(array("\r\n", "\r"), "\n", $posted_value);
			$shortcodes = array();
			foreach(explode("\n", $posted_value) as $shortcode){
				$shortcode = trim($shortcode);
				if( !empty($shortcode) && preg_match('#[<>&/\[\]\x00-\x20=]#', $shortcode) === 0 ){
					$shortcodes[$shortcode] = 1;
				}
			}
			$shortcodes = array_keys($shortcodes);
			if( update_option(self::OPTION_NAME, $shortcodes) ){
				$info = "<div class=\"updated notice notice-success\"><p>Updated.</p></div>";
			}else{
				$info = "<div class=\"error notice notice-error\"><p>Not updated.</p></div>";
			}
		}

		$shortcodes_text = implode("\n", $shortcodes);
		$nonce = wp_create_nonce(plugin_basename(__FILE__));
		echo <<< EOD
<div class="wrap">
	<h2>Wpautop Mask Settings</h2>
	{$info}
	<form action="" method="post">
		<div style="margin:16px 0 0 0;">
			<p>Automatic formatting (wpautop) is disabled between specific shortcode tags: [wpautop_mask], [wpautop_mask_h] and the following.</p>
			<textarea name="shortcodes" cols="24" rows="8" placeholder="shortcode_1
shortcode_2
shortcode_3">{$shortcodes_text}</textarea>
		</div>
		<div style="margin:16px 0 0 0;">
			<input type="hidden" name="nonce" value="{$nonce}" />
			<input type="submit" value="Save" class="button button-primary" />
		</div>
	</form>
	<div class="card">
		<h3 class="title">Example 1</h3>
		<p>[wpautop_mask] only disables wpautop.</p>
		<h4>Original (in Text mode editor)</h4>
		<pre style="background:rgba(0,0,0,0.1) none;padding:2.4%;">sample text
[wpautop_mask]sample text[/wpautop_mask]
sample text
sample text</pre>
		<h4>Display</h4>
		<pre style="background:rgba(0,0,0,0.1) none;padding:2.4%;">&lt;p&gt;sample text&lt;/p&gt;
sample text
&lt;p&gt;sample text&lt;br /&gt;
sample text&lt;/p&gt;</pre>
	</div>
	<div class="card">
		<h3 class="title">Example 2</h3>
		<p>[wpautop_mask_h] disables wpautop and escapes HTML special characters.</p>
		<h4>Original (in Text mode editor)</h4>
		<pre style="background:rgba(0,0,0,0.1) none;padding:2.4%;">&lt;div&gt;sample html
sample html&lt;/div&gt;
[wpautop_mask_h]&lt;div&gt;sample html
sample html&lt;/div&gt;[/wpautop_mask_h]</pre>
		<h4>Display</h4>
		<pre style="background:rgba(0,0,0,0.1) none;padding:2.4%;">&lt;div&gt;sample html&lt;br /&gt;
sample html&lt;/div&gt;
&lt;pre&gt;&lt;code&gt;&amp;lt;div&amp;gt;sample html
sample html&amp;lt;/div&amp;gt;&lt;/code&gt;&lt;/pre&gt;</pre>
	</div>
</div>
EOD;
	}
}

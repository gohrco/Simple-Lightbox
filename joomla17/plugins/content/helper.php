<?php


class SimpleLightboxHelper
{
	
	public	$abs_path	= null;
	public	$abs_url	= null;
	
	public	$allow_rebuild	= false;
	
	public	$javascript	= proto;
	
	public	$plg_folder	= null;
	public	$plg_url	= null;
	
	public	$regex_images	= null;
	public	$regex_website	= null;
	
	public	$thumbnails			= null;
	public	$watermarks			= null;
	public	$watermark_factor	= 0;
	public	$watermark_image	= null;
	
	private	$_active	= true;
	private	$_error		= null;
	private	$_params	= null;
	private	$_thumblist	= array();
	
	/**
	 * Constructor method
	 * @access		public
	 * @version		@fileVers@
	 * 
	 * @since		1.0.0
	 */
	public function __construct( $params )
	{
		// Store params
		$this->_params	= $params;
		
		// Type of JS to use
		$this->javascript	= $params->get( 'jstype' );
		
		// Absolutes
		$this->abs_path	= JPATH_BASE;
		$this->abs_url	= rtrim( JUri :: base(), '/ ' );
		
		// Additionals
		$ds	= DIRECTORY_SEPARATOR;
		$this->plg_folder	= $this->abs_path	. $ds . 'plugins' . $ds . 'content' . $ds . 'simplelightbox';
		$this->plg_url		= $this->abs_url	. '/plugins/content/simplelightbox';
		$this->thumbnails	= $this->plg_folder . $ds . 'thumbs' . $ds;
		$this->watermarks	= $this->plg_folder . $ds . 'watermarks' . $ds;
		$this->slash_path	= $this->_getSlashPath();
		
		// Check Requirements and set regex
		if(! $this->_checkRequirements() ) return;
		$this->_setRegex();
		
		$this->_setPermissions();
		$this->_setDocument();
		$this->_setWatermark();
		$this->_rebuildThumbs();
		
	}
	
	
	public function getError()
	{
		$error	= $this->_error;
		$this->_error	= null;
		return $error;
	}
	
	
	public function getInstance( $params = null )
	{
		static $instance = null;
		
		if (! is_object( $instance ) ) {
			$instance = new self( $params );
		}
		
		return $instance;
	}
	
	
	public function getParams()
	{
		return $this->_params;
	}
	
	
	public function isActive()
	{
		return $this->_active;
	}
	
	
	public function loadData( $data )
	{
		// Get the images and return if none exist
		preg_match_all( $this->regex_images, $data, $images, PREG_SET_ORDER );
		
		switch ( count( $images ) ) :
		case 0:
			return false;
		case 1:
			$rel_item = "lightbox";
			break;
		default:
			$rel_item = "lightbox[" . uniqid("") . "]";
			break;
		endswitch;
		
		// Split the content into pieces and start assembly of final content
		$data_chunks	= preg_split( $this->regex_images, $data );
		$final_data		= array_shift( $data_chunks );
		
		for ( $i = 0; $i < count( $images ); $i++ ) {
			// Clean up the found image match
			$imgobj		= new SimpleLightboxImage( $images[$i] );
			
			// Determine how we should bother resizing
			$resize	= 0;
			if ( isset( $imgobj->attrib_thumb['width'] ) ) $resize += 1;
			if ( isset( $imgobj->attrib_thumb['height'] ) ) $resize += 2;
			
			// No width or height present, so no resize possible...
			if ( $resize == 0 ) {
				$final_data	.= $imgobj->str_match . $data_chunks[$i];
				continue;
			}
			
			// Store the img object
			$images[$i] = $imgobj;
			
			// Determine the source file
			$source_file = null;
			$source_url		= isset( $imgobj->attrib_thumb['src'] ) ? $imgobj->attrib_thumb['src'] : null;
			$source_url		= trim( $source_url );
			$source_url2	= rtrim( $source_url, '\.\\/' );
			
			if ( strlen( $source_url2 ) < strlen( $source_url ) ) $source_url = null;
			
			if ( $source_url ) {
				if ( preg_match( '#^[^:/]*://#', $source_url ) ) {
					if ( preg_match( $this->regex_website, $source_url, $matches ) ) {
						$source_file = $matches[1];
					}
				}
				else {
					$source_file = $source_url;
					if ( $source_file[0] == "/" ) {
						if ( strncasecmp( $source_file, $this->slash_path, strlen( $this->slash_path ) ) == 0 ) {
							$source_file = substr( $source_file, strlen( $this->slash_path ) );
						}
						else {
							$source_file = null;
						}
					}
				}
			}
			
			// Test to see if the source file exists
			if ( ( $source_file = $this->_checkSourceFile( $source_file ) ) === false ){
				unset( $source_file );
				$final_data	.= $imgobj->str_match . $data_chunks[$i];
				continue;
			}
			
			// We verified the source exists, so lets test what to do
			list( $source_width, $source_height, $source_extension ) = getimagesize( $source_file );
			$dest_width = $dest_height = 1;
			$skip		= true;
			
			if ( ( $resize & 1 ) == 1 ) {
				$imgobj->attrib_thumb['width'] = max( $imgobj->attrib_thumb['width'], 1 );
				if ( $source_width > $imgobj->attrib_thumb['width'] ) {
					$skip		= false;
					$dest_width	= $imgobj->attrib_thumb['width'];
				}
				else {
					$dest_width	= $source_width;
				}
			}
			
			if ( ( $resize & 2 ) == 2 ) {
				$imgobj->attrib_thumb['height'] = max( $imgobj->attrib_thumb['height'], 1 );
				if ( $source_height > $imgobj->attrib_thumb['height'] ) {
					$skip			= false;
					$dest_height	= $imgobj->attrib_thumb['height'];
				}
				else {
					$dest_height	= $source_height;
				}
			}
			
			// We aren't resizing so skip this
			if ( $skip ) {
				$final_data	.= $imgobj->str_match . $data_chunks[$i];
				continue;
			}
			
			switch( $resize ) :
				case 1:
					$ratio			= $source_width / $dest_width;
					$dest_height	= ceil( $source_height / $ratio );
					break;
				case 2:
					$ratio			= $source_height / $dest_height;
					$dest_width		= ceil( $source_width / $ratio );
					break;
				case 3:
				default:
					$ratio	= sqrt( $source_width * $source_height / ( $dest_width * $dest_height ) );
			endswitch;
			
			$ratio_inf	= 1 + $this->_params->get( 'fb_threshold_percent' ) / 100;
			$ratio_inf	= ( $ratio_inf < 1 ? 1 : ( $ratio_inf > 2 ? 2 : $ratio_inf ) );
			
		if ( $ratio < $ratio_inf ) { $imgobj->_nosl = true; $this->_nosl = true; }
			
			$thumbnail_file	= $this->_createThumbFile( $source_file, $dest_width, $dest_height );
			$dest_file		= $this->thumbnails . $thumbnail_file;
			
			$use_watermark	=
			( 
				( (bool) $this->watermark_image &&
				(! $imgobj->nosl() ) ) &&
				( 
					( ( $dest_width / $this->watermark_width ) > $this->watermark_factor ) || 
					( ( $dest_height / $this->watermark_height ) > $this->watermark_factor )
				 )
			);
			
			if (! file_exists( $dest_file ) ) {
				// Create thumbnail image, if failed loop again
				if (! $this->_createThumbImage( $dest_file, $dest_width, $dest_height, $source_file, $use_watermark ) ) {
					$final_data	.= $imgobj->str_match . $data_chunks[$i];
					continue;
				}
			}
			
			$dest_url	= $this->plg_url . '/thumbs/' . $thumbnail_file;
			$imgobj->attrib_thumb['src'] = $dest_url;
			if ( defined( '_SLREFRESH_ID' ) ) $imgobj->attrib_thumb['src'] .= '?refresh_id=' . _SLREFRESH_ID;
			
			$img_attribs	= array();
			foreach( $imgobj->attrib_thumb as $key => $value ) $img_attribs[] = $key . '="' . $value . '"';
			
			$img_tag = '<img ' . implode( ' ', $img_attribs ) . ' />';
			
			if (! $imgobj->nosl() ) {
				$img_tag = '<a href="' . $source_url . '" rel="' . $rel_item . '" target="_blank">' . "\n" . $imgobj->get( 'sltag' ) . "\n" . $img_tag . "\n</a>";
			}
			
			$final_data	.= $img_tag . $data_chunks[$i];
		}
		
		return $final_data;
	}
	
	
	public function loadRefreshButton()
	{
		$form	= null;
		if ( $this->allow_rebuild && count( $this->_thumblist ) > 0 ) {
			$uri	= JFactory :: getUri();
			$return	= $uri->toString();
			$list	= base64_encode( gzcompress( serialize( $this->_thumblist ) ) );
			$form	= <<< FORM
<hr noshade="noshade" />
<form method="post" action="{$return}">
	<input type="hidden" name="simplelightbox_rebuild_thumbs_list" value="{$list}" />
	<input type="hidden" name="simplelightbox_rebuild_thumbs" value="1" />
	<input type="submit" name="Submit" value="Rebuild Thumbnails" class="button" />
</form>
FORM;
		}
		return $form;
	}
	
	
	private function _checkRequirements()
	{
		if (! function_exists( 'getimagesize' ) ) {
			$this->_error	= "<hr noshade='noshade'/>\n<b>Error:</b>Your php install does not support <i>getimagesize</i> function </br>This is a requirement for SimpleLightbox";
			$this->_active	= false;
			return false;
		}
		
		if (! function_exists( 'imagecreatefromjpeg' ) ) {
			$this->_error	= "<hr noshade='noshade'/>\n<b>Error:</b>Your php install does not support GD.</br>This is a requirement for SimpleLightbox";
			$this->_active	= false;
			return false;
		}
		
		if (! is_dir( $this->thumbnails ) ) {
			$this->_error	= "<hr noshade=\"noshade\"/>\n<b>Error:</b> Unable to find thumbnails folder `{$this->thumbnails}` for SimpleLightbox";
			$this->_active	= false;
			return false;
		}
		
		if (! function_exists( 'imagecreatetruecolor' ) ) {
			$this->_params->set( 'resize_method', 'gd1' );
		}
		
		// Be sure we don't go over or under 0 / 100 for jpg quality
		if ( $this->_params->get( 'jpg_quality' ) > 100 ) {
			$this->_params->set( 'jpg_quality', '100' );
		}
		elseif ( $this->_params->get( 'jpg_quality' ) < 0 ) {
			$this->_params->set( 'jpg_quality', '0' );
		}
		
		return true;
	}
	
	
	private function _checkSourceFile( $source_file )
	{
		$run			= true;
		$source_file	= trim(rawurldecode( $source_file ) );
		
		do {
			// Test to see if source file exists
			$source_exists	= $source_file ? true : false;
			if (! $source_exists ) break;
			
			if ( file_exists( $this->abs_path . DIRECTORY_SEPARATOR . $source_file ) ) {
				$source_file = $this->abs_path . DIRECTORY_SEPARATOR . $source_file;
				break;
			}
			
			if (! ( $source_exists = $this->_detectUTF8( $source_file ) ) ) break;
			
			$tmp	= trim( utf8_decode( $source_file ) );
			if (! ( $source_exists = utf8_encode( $tmp ) == $source_file ) ) break;
			
			$source_exists	= file_exists( $this->abs_path . DIRECTORY_SEPARATOR . $tmp ); 
			$run			= false;
		} while ( $run );
		
		return $source_exists ? $source_file : false;
	}
	
	
	private function _createThumbFile( $source_file, $w, $h )
	{
		$temp	= preg_replace( '#^.*/(.*)$#','\1', $source_file ); //remove path
		$temp	= preg_replace( '#^(.*)\..*$#','\1', $temp ); //remove extension
		
		if ( $this->_detectUTF8( $temp ) ) {
			$temp = utf8_decode($temp);
		}
		
		$temp = $this->_transliterate( $temp );
		$temp = preg_replace( '#[^a-zA-Z0-9]+#','-', $temp );
		
		$strs	= explode('-', $temp);
		$temp	= '';
		$n		= count( $strs );
		$j		= 0;
		$test_cur_small = $test_prev_small = false;
		
		for ( $k=0; $k < $n; $k++ ) {
			$str	= $strs[$k];
			$l		= strlen( $str );
			
			if ( $l == 0 ) continue;
			
			$inter	= '-';
			$test_cur_small = $l < 3;
			
			if ( $l < 5 && $j > 2 && ( $test_prev_small || $test_cur_small ) ) {
				$inter = '';
			}
			
			if ( $test_cur_small ) {
				$j++;
			}
            
			$test_prev_small = $test_cur_small;
			$temp = $temp . $inter . $str;
		}
		
		$temp	= trim( $temp,'-' );
		$temp	= substr( $temp, 0, 64 );
		$temp	= trim( $temp, '-' );
		
		if ( strlen( $temp ) == 0 ) $temp='thumb';
        
		$md5_src_file = md5( $source_file );
		
		if ( $this->allow_rebuild ) $this->_thumblist[$md5_src_file] = true;
        return	$temp . '_' . $w . "x" . $h . '_' . $md5_src_file . '.jpg';
	}
	
	
	private function _createThumbImage( $thumb, $w, $h, $source_file, $watermark = true )
	{
		list( $source_width, $source_height, $source_extension ) = getimagesize( $source_file );
		$dest_image		= $source_image = false;
		
		switch ( $this->_params->get( 'resize_method' ) ) :
		case 'gd1' :
			if ( $source_extension == 2 ) $source_image = imagecreatefromjpeg( $source_file );
			else $source_image = imagecreatefrompng( $source_file );
			if (! source_image ) break;
			$dest_image = imagecreate( $w, $h );
			imagecopyresized( $dest_image, $source_image, 0, 0, 0, 0, $w, $h, $source_width, $source_height );
			break;
		case 'gd2' :
		default:
			if ( $source_extension == 1 && function_exists( 'imagecreatefromgif' ) ) {
				$source_image = imagecreatefromgif( $source_file );
				imagecolortransparent( $source_image );
			}
			else if ( $source_extension == 2 ) $source_image = imagecreatefromjpeg( $source_file );
			else {
				$source_image = imagecreatefrompng( $source_file );
				imagecolortransparent( $source_image );
			}
			
			if (! $source_image ) break;
			if ( $source_extension == 1 ) {
			$dest_image = imagecreate( $w, $h );
			}
			else {
				$dest_image	= imagecreatetruecolor( $w, $h );
				if ( $watermark ) imagealphablending( $dest_image, true );
			}
			imagecopyresampled( $dest_image, $source_image, 0, 0, 0, 0, $w, $h, $source_width, $source_height );
			break;
		endswitch;
		
		if (! $source_image ) {
			return false;
		}
		
		if ( $watermark ) {
			$dest_x	= round( $this->watermark_posx * ( $w - $this->watermark_width ) );
			$dest_y = round( $this->watermark_posy * ( $h - $this->watermark_height ) );
			$src_x	= $dest_x < 0 ? -$dest_x : 0;
			$src_y	= $dest_y < 0 ? -$dest_y : 0;
			$dest_x	= $dest_x < 0 ? 0 : $dest_x;
			$dest_y = $dest_y < 0 ? 0 : $dest_y;
			$src_w	= min( $this->watermark_width - $src_x, $w );
			$src_h	= min( $this->watermark_height - $src_y, $h );
			imagecopy( $dest_image, $this->watermark_image, $dest_x, $dest_y, $src_x, $src_y, $src_w, $src_h );
		}
		
		touch( $thumb );
		imagejpeg( $dest_image, $thumb, $this->_params->get( 'jpg_qual' ) );
		imagedestroy( $source_image );
		imagedestroy( $dest_image );
		
		return true;
	}
	
	
	private function _detectUTF8( $data )
	{
		return preg_match('%(?:
			[\xC2-\xDF][\x80-\xBF]                 # non-overlong 2-byte
			|\xE0[\xA0-\xBF][\x80-\xBF]            # excluding overlongs
			|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}     # straight 3-byte
			|\xED[\x80-\x9F][\x80-\xBF]            # excluding surrogates
			|\xF0[\x90-\xBF][\x80-\xBF]{2}         # planes 1-3
			|[\xF1-\xF3][\x80-\xBF]{3}             # planes 4-15
			|\xF4[\x80-\x8F][\x80-\xBF]{2}         # plane 16
		)+%xs', $data );
	}
	
	
	private function _getSlashPath()
	{
		$tmp	= str_replace( "://", "#", $this->abs_url );
		$tmp	= explode( "/", $tmp );
		$tmp[0]	= "";
		$this->slash_path	= implode( "/", $tmp );
	}
	
	
	private function _rebuildThumbs()
	{
		// Check to see if we can rebuild or want to
		$rebuild_request	= (    (bool) JRequest :: getVar( 'simplelightbox_rebuild_thumbs', 0, 'post', 'int' )
								&& $this->allow_rebuild 
								&& (bool) JRequest :: getVar( 'simplelightbox_rebuild_thumbs_list', false, 'post' ) 
		);
		
		// Can't, or wont rebuild so return
		if (! $rebuild_request ) {
			$this->_params->set( 'rebuild_button', false );
			return;
		}
		
		// Get the list of thumbs to rebuild
		$rebuild_list	= @unserialize( gzuncompress( base64_decode( JRequest :: getVar( 'simplelightbox_rebuild_thumbs_list', false, 'post' ) ) ) );
		
		// If the list isn't a list or is empty return
		if (! is_array( $rebuild_list ) || count( $rebuild_list ) == 0 ) return;
		
		foreach( $rebuild_list as $key => $value ) {
			if ( $value != 1 || ! preg_match( '#^[a-fA-F0-9]{32}$#', $key ) ) return;
		}
		
		$dir	= opendir( $this->thumbnails );
		$regex	= '#^[a-zA-Z0-9\-]{1,64}_[0-9]+x[0-9]+_([a-fA-F0-9]{32})\.jpg$#';
		$error	= array();
		
		while( $file = readdir( $dir ) ) {
			if ( is_dir( $file ) || in_array( $file, array( '.', '..' ) ) ) continue;
			if ( preg_match( $regex, $file, $matches ) && isset( $rebuild_list[$matches[1]] ) ) {
				if (! @unlink( $this->thumbnails . $file ) ) {
					$error[]	= "<b/>Warning!:</b> Unable to delete file:\"$file\"  <br />";
				}
			}
		}
		
		closedir( $dir );
		clearstatcache();
		
		$this->_error	= implode( "\n", $error );
		if (! (bool) $this->_error ) define( '_SLREFRESH_ID', uniqid("") );
		return;
	}
	
	
	private function _setDocument()
	{
		$document	= & JFactory :: getDocument();
		
		switch ( $this->javascript ):
		case 'proto':
			$document->addStyleSheet('plugins/content/simplelightbox/assets/css/lightbox.css');
			$document->addScript('plugins/content/simplelightbox/assets/js/prototype.js');
			$document->addScript('plugins/content/simplelightbox/assets/js/scriptaculous.js?load=effects,builder');
			$document->addScript('plugins/content/simplelightbox/assets/js/lightbox.js');
        	break;
		case 'moo':
			$document->addStyleSheet( 'plugins/content/simplelightbox/assets/css/slimbox.css' );
			$document->addScript( 'plugins/content/simplelightbox/assets/js/slimbox.js' );
			break;
		endswitch;
	}
	
	
	private function _setPermissions()
	{
		$juser	= & JFactory :: getUser();
		$this->allow_rebuild	= $juser->authorise( 'core.manage' ) && function_exists( 'gzcompress' ) && $this->_params->get( 'rebuild_button' );
		return;
	}
	
	
	private function _setRegex()
	{
		$this->regex_images	= '#<img((?:\s+[a-zA-Z0-9:\-_/]+(?:\s*=(?:\s*"[^"]*")?)?)+)\s*/?\s*>#i';
		
		if ( preg_match( '#^([^:/]*://)(?:www\.)?(.*)$#i', $this->abs_url, $matches ) ) {
			$websiteRegex	= preg_quote( $matches[1],'#' ) . '(?:www\.)?' . preg_quote( $matches[2],'#' );
		}
		else {
			$websiteRegex	= preg_quote( $this->abs_url,'#' );
		}
		
		$this->regex_website	= '#^' . $websiteRegex . '((?:/.*)?)$#i';
	}
	
	
	private function _setWatermark()
	{
		$filename	= $this->watermarks . trim( $this->_params->get( 'watermark' ), ' /' );
		$width		= $height 
					= $extension 
					= 0;
		
		$factor		= 1 + ( $this->_params->get( 'wtmk_threshold_percent' ) / 100 );
		$factor		= ( $factor < 0 ? 0 : ( $factor > 100 ? 100 : $factor ) );
		
		$posx		= $this->_params->get( 'wtmk_pos_x_percent' ) / 100;
		$posx		= ( $posx < 0 ? 0 : ( $posx > 1 ? 1 : $posx ) );
		
		$posy		= $this->_params->get( 'wtmk_pos_y_percent' ) / 100;
		$posy		= ( $posy < 0 ? 0 : ( $posy > 1 ? 1 : $posy ) );
		
		if ( strtolower( strrchr( $filename, '.png' ) ) == '.png' && file_exists( $filename ) ) {
			list( $width, $height, $extension ) = @getimagesize( $filename );
			if ( $extension == 3 ) {
				$this->watermark_image = imagecreatefrompng( $filename );
			}
		}
		
		$this->watermark_factor	= $factor;
		$this->watermark_posx	= $posx;
		$this->watermark_posy	= $posy;
		$this->watermark_width	= $width;
		$this->watermark_height	= $height;
	}
	
	
	private function _transliterate( $data )
	{
		$string = htmlentities( $data );
		$string = preg_replace(
					array('/&szlig;/','/&(..)lig;/', '/&([aouAOU])uml;/','/&(.)[^;]*;/'),
					array('ss',"$1","$1".'e',"$1"),
					$string
		);
		return $string;
	}
	
}


class SimpleLightboxImage
{
	public	$attributes		= array();
	public	$attrib_thumb	= array();
	
	public	$sltag			= null;
	public	$str_match		= null;
	public	$str_attribs	= null;
	
	public $_nosl			= false;
	
	public function __construct( $data = array() )
	{
		$this->str_match	= $data[0];
		$this->str_attribs	= $data[1];
		
		$slobj = SimpleLightboxHelper :: getInstance();
		$this->_params = $slobj->getParams();
		
		$this->_parseAttribs( $data[1] );
	}
	
	
	public function get( $var, $default = null )
	{
		return ( isset( $this->$var ) ? $this->$var : $default );
	}
	
	
	public function nosl()
	{
		return $this->_nosl;
	}
	
	
	private function _fixStyle( $attribs )
	{
		$style	= $attribs['style'];
		$style	= ';' . $style . ';';
		
		$wregex	= '/;\s*width\s*:\s*([0-9]+)\s*px\s*;/i';
		preg_match_all( $wregex, $style, $matches );
		$matches = $matches[1];
		
		if ( count( $matches) > 0 ) {
			$attribs['width'] = $matches[count($matches)-1];
		}
		$style = preg_replace( $wregex, ';', $style );
		
		$hregex = '/;\s*height\s*:\s*([0-9]+)\s*px\s*;/i';
		preg_match_all( $hregex, $style, $matches );
		$matches = $matches[1];
		
		if ( count( $matches ) > 0 ) {
			$attribs['height'] = $matches[count($matches)-1];
		}
		
		$style = preg_replace( $hregex, ';', $style );
		$style = trim( $style, '; ' );
		
		if ( $style == '' ) {
			unset( $attribs['style'] );
		}
		else {
			$attribs['style'] = $style . ';';
		}
		
		return $attribs;
	}
	
	
	private function _parseAttribs( $data )
	{
		$attribs	= null;
		
		preg_match_all( '#([a-zA-Z0-9:\-_]+)(?:\s*=(?:\s*"([^"]*)")?)?#',$data, $attribs, PREG_SET_ORDER );
		
		$attribs		= array_reverse( $attribs );
		$thumbAttribs	= array();
		$plgAttribs		= array();
		
		for ( $i = 0; $i < count ( $attribs ); $i ++ ) {
			// For standalone attribs, set an empty value
			if (! isset( $attribs[$i][2] ) ) $attribs[$i][2] = '';
			
			// Go lowercase
			$attribs[$i][1] = strtolower( $attribs[$i][1] );
			
			// Find sl attributes
			$tmp = explode( ':', $attribs[$i][1], 2 );
			if ( $tmp[0] == 'sl' ) {
				if ( isset( $tmp[1] ) ) {
					$plgAttribs[$tmp[1]] = $attribs[$i][2];
				}
			}
			else {
				$thumbAttribs[$attribs[$i][1]] = $attribs[$i][2];
			}
		}
		
		if ( isset( $plgAttribs['src'] ) ) {
			$thumbAttribs['src'] = $plgAttribs['src'];
			unset( $plgAttribs['src'] );
		}
		
		if ( isset( $thumbAttribs['nosl'] ) ) {
			$this->_nosl = true;
			unset( $thumbAttribs['nosl'] );
		}
		
		if ( isset( $plgAttribs['nosl'] ) ) $this->_nosl = true;
		
		$slTag	= null;
		
		if (! $this->_nosl ) {
			if ( isset( $plgAttribs['name'] ) ) $auto_next_prev = false;
			if ( $auto_next_prev ) $plgAttribs['name'] = $auto_next_prev_name;
			
			if ( ( (bool) $this->_params->get( 'force_caption' ) ) && ! isset( $plgAttribs['title'] ) && ! isset( $thumbAttribs['title'] ) )  {
				$plgAttribs['title'] = '.';
			}
			
			$tmp = array();
			foreach( $plgAttribs as $key => $value ) {
				$tmp[] = $key . '="' . $value . '"';
			}
			$slTag = implode( ' ', $tmp );
		}
		
		unset( $plgAttribs );
		
		if ( isset( $thumbAttribs['style'] ) ) $thumbAttribs = $this->_fixStyle( $thumbAttribs );
		
		$this->sltag		= $slTag;
		$this->attributes	= $attribs;
		$this->attrib_thumb = $thumbAttribs;
		
	}
}
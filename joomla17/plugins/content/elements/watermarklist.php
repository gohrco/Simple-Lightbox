<?php
/**
 * SimpleLightbox - Content Plugin
 * 
 * @package    @projectName@
 * @copyright  @copyWrite@
 * @license    @buildLicense@
 * @version    $Id$
 * @since      1.0.0
 * 
 * @desc		This element retrieves a list of watermark filenames located in the
 * 				plugin directory that contains the watermarks.
 */


/*-- Security Protocols --*/
defined('JPATH_BASE') or die;
/*-- Security Protocols --*/

/*-- File Inclusions --*/
jimport('joomla.html.html');
jimport('joomla.form.formfield');
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');
/*-- File Inclusions --*/

/**
 * JFormFieldWatermarkList class
 * @version		@fileVers@
 * 
 * @since		1.0.0
 * @author		Steven
 */
class JFormFieldWatermarkList extends JFormFieldList
{
	/**
	 * The type of this element
	 * @access		protected
	 * @since		1.0.0
	 * @var			string
	 */
	protected $type = 'WatermarkList';
	
	
	/**
	 * Gets the options for the list object
	 * @access		protected
	 * @version		@fileVers@
	 * 
	 * @return		array of objects
	 * @since		1.0.0
	 */
	protected function getOptions()
	{
		$ds	= DIRECTORY_SEPARATOR;
		$watermark_path	= JPATH_ROOT . $ds . 'plugins' . $ds . 'content' . $ds . 'simplelightbox' . $ds . 'watermarks' . $ds;
		
		$dir	= opendir( $watermark_path );
		$regex	= '#^[a-zA-Z0-9\-]{1,64}\.png$#';
		
		$data	= array( (object) array( 'value' => 'none', 'text' => '- none -' ) );
		while( $file = readdir( $dir ) ) {
			if ( is_dir( $file ) || in_array( $file, array( '.', '..', 'index.html' ) ) ) continue;
			if ( preg_match( $regex, $file, $matches ) ) {
				$data[]	= (object) array( 'value' => $file, 'text' => $file );
			}
		}
		
		closedir( $dir );
		
		return $data;
	}
}

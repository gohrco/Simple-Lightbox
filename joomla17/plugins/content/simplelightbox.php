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
 * @desc		This plugin parses the content and if set to replaced images with a link
 * 				and thumbnail that pops up the full sized image in a lightbox.  It is
 * 				designed to be simple and unobtrusive and to function for both Joomla
 * 				content and K2 content items.
 * 
 * @filesource	This package relies upon the `Lightbox 2` package by Lokesh Dhakar
 * 					http://lokeshdhakar.com/projects/lightbox2/
 * 				The package also has borrowed extensively from the plg_fbox plugin by Mehdi
 * 					http://www.mehdiplugins.com/misc/fboxbot.htm
 */


/*-- Security Protocols --*/
defined('_JEXEC') or die( 'Restricted access' );
/*-- Security Protocols --*/

/*-- File Inclusions --*/
jimport('joomla.plugin.plugin');
require_once( 'helper.php' );
/*-- File Inclusions --*/

/**
 * Content Plugin
 * @version		@fileVers@
 * 
 * @since		1.0.0
 * @author		Steven
 */
class plgContentSimplelightbox extends JPlugin {

	/**
	 * Constructor method
	 * @access		public
	 * @version		@fileVers@
	 * @param		object		- $subject:  The object to observe
	 * @param 		array		- $config:  An array that holds the plugin configuration
	 * 
	 * @since		1.0.0
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct( $subject, $config );
	}
	
	
	/**
	 * on Content Prepare task
	 * @access		public
	 * @version		@fileVers@
	 * @param		string		- $context: ie text
	 * @param		object		- $row: contains the article object
	 * @param		object		- $params: simplified parameter container
	 * @param		int			- $page: which page we are on
	 * 
	 * @since		1.0.0
	 */
	public function onContentPrepare( $context, $row, $params, $page = 0 )
	{
		// Catch errors before they are problems :)
		if (! is_object( $row ) ) return true;
		if (! isset( $row->text ) ) return true;
		
		// Catch admins (we don't need in backend)
		$app	= & JFactory :: getApplication();
		if ( $app->isAdmin() ) return true;
		
		// Grab the helper object
		$slb	= & SimpleLightboxHelper :: getInstance( $this->params );
		
		// If inactive bail
		if (! $slb->isActive() ) {
			$row->text	.= "\n\n{$slb->getError()}";
			return true;
		}
		
		// No images found in data so return
		if (! ( $data = $slb->loadData( $row->text ) ) ) {
			return true;
		}
		
		$row->text = $data;
		
		// Grab the refresh cache button if necessary
		$row->text .= $slb->loadRefreshButton();
		
		return true;
	}
}
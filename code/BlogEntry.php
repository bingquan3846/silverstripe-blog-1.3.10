<?php
/**
 * An individual blog entry page type.
 * 
 * @package blog
 */
class BlogEntry extends Page {
	static $db = array(
		"Date" => "SS_Datetime",
		"Author" => "Text",
		"Tags" => "Text",
	);
	
	static $default_parent = 'BlogHolder';
	
	static $can_be_root = false;
	
	static $icon = "sapphire/javascript/tree/images/page";

	static $description = "An individual blog entry";
	
	static $singular_name = 'Blog Entry Page';
	
	static $plural_name = 'Blog Entry Pages';
		
	static $has_one = array('BaseImage' => 'Image');
	
	static $has_many = array();
	
	static $many_many = array();
	
	static $belongs_many_many = array();
	
	static $defaults = array(
		"ProvideComments" => true,
		'ShowInMenus' => false
	);
	
	static $extensions = array(
		'TrackBackDecorator'
	);
		
	/**
	 * Is WYSIWYG editing allowed?
	 * @var boolean
	 */
	static $allow_wysiwyg_editing = true;
	
	/**
	 * Overload so that the default date is today.
	 */
	public function populateDefaults(){
		parent::populateDefaults();

		$this->setField('Date', date('Y-m-d H:i:s', strtotime('now')));
	}
	
	function getCMSFields() {
		Requirements::javascript('blog/javascript/bbcodehelp.js');
		Requirements::themedCSS('bbcodehelp');

		$firstName = Member::currentUser() ? Member::currentUser()->getName() : '';

		$codeparser = new BBCodeParser();
		
		SiteTree::disableCMSFieldsExtensions();
		$fields = parent::getCMSFields();
		SiteTree::enableCMSFieldsExtensions();
		
		if(!self::$allow_wysiwyg_editing) {
			$fields->removeFieldFromTab("Root.Properties","Content");
			$fields->addFieldToTab("Root.Properties", new TextareaField("Content", _t("BlogEntry.CN"), 20));
		}
		
		$fields->addFieldToTab("Root.Properties", $dateField = new DatetimeField("Date", _t("BlogEntry.DT", "Date")));
		$dateField->getDateField()->setConfig('showcalendar', true);
		$dateField->getTimeField()->setConfig('showdropdown', true);

        $authorText = new TextField("Author", _t("BlogEntry.AU", "Author"), $firstName);


		$fields->addFieldToTab("Root.Properties", $authorText);

		if(!self::$allow_wysiwyg_editing) {
			$fields->addFieldToTab("Root.Properties", new LiteralField("BBCodeHelper", "<div id='BBCode' class='field'>" .
							"<a  id=\"BBCodeHint\" target='new'>" . _t("BlogEntry.BBH", "BBCode help") . "</a>" .
							"<div id='BBTagsHolder' style='display:none;'>".$codeparser->useable_tagsHTML()."</div></div>"));
		}
				
		$fields->addFieldToTab("Root.Properties", new TextField("Tags", _t("BlogEntry.TS", "Tags (comma sep.)")));

/*        $image = new ImageField('BaseImage', 'Detail background image');
        $fields->addFieldToTab('Root.Properties', $image);*/
		
		$this->extend('updateCMSFields', $fields);
        //var_dump($fields);die;
		
		return $fields;
	}

	
	/**
	 * Returns the tags added to this blog entry
	 */
	function TagsCollection() {

		$tags = preg_split(" *, *", trim($this->Tags));
		$output =  array();
		
		$link = $this->getParent()->getParent() ? $this->getParent()->getParent()->Link('tag') : '';
		foreach($tags as $tag) {
			array_push($output, new ArrayData(array(
				'Tag' => Convert::raw2xml($tag),
				'Link' => $link . '/' . urlencode($tag),
				'URLTag' => urlencode($tag)
			)));
		}
		if($this->Tags) {
			return (object)$output;
		}
	}

	function Content() {	
		if(self::$allow_wysiwyg_editing) {
			return $this->getField('Content');
		} else {
			$parser = new BBCodeParser($this->Content);
			$content = new HTMLText('Content');
			$content->value = $parser->parse();
			return $content;
		}
	}
	
	/**
	 * To be used by RSSFeed. If RSSFeed uses Content field, it doesn't pull in correctly parsed content. 
	 */ 
	function RSSContent() {
		return $this->Content();
	}


	/**
	 * Get a bbcode parsed summary of the blog entry
	 * @deprecated
	 */
	function ParagraphSummary(){
		user_error("BlogEntry::ParagraphSummary() is deprecated; use BlogEntry::Content()", E_USER_NOTICE);
		
		$val = $this->Content(); 
		$content = $val; 
		
		if(!($content instanceof HTMLText)) {
			$content = new HTMLText('Content');
			$content->value = $val;
		}

		return $content->FirstParagraph('html');
	}
	
	/**
	 * Get the bbcode parsed content
	 * @deprecated
	 */
	function ParsedContent() {
		user_error("BlogEntry::ParsedContent() is deprecated; use BlogEntry::Content()", E_USER_NOTICE);
		return $this->Content();
	}
	
	/**
	 * Link for editing this blog entry
	 */
	function EditURL() {
		return ($this->getParent()) ? $this->getParent()->Link('post') . '/' . $this->ID . '/' : false;
	}
    /**
     * Link for return to parent tree (BBQ)
     */
    function ParentURL(){
        return $this->getParent()->getParent()->Link();
    }
	
	/**
	 * Check to see if trackbacks are enabled.
	 */
	function TrackBacksEnabled() {
		return ($this->getParent()) ? $this->getParent()->TrackBacksEnabled : false;
	}
	
	function trackbackping() {
		if($this->TrackBacksEnabled() && $this->hasExtension('TrackBackDecorator')) {
			return $this->decoratedTrackbackping();
		} else {
			Controller::curr()->redirect($this->Link());
		}
	}

	function IsOwner() {
		if(method_exists($this->Parent(), 'IsOwner')) {
			return $this->Parent()->IsOwner();
		}
	}
	
	/**
	 * Call this to enable WYSIWYG editing on your blog entries.
	 * By default the blog uses BBCode
	 */
	static function allow_wysiwyg_editing() {
		self::$allow_wysiwyg_editing = true;
	}
	
	
	/**
	 * Get the previous blog entry from this section of blog pages. 
	 *
	 * @return BlogEntry
	 */
	function PreviousBlogEntry() {
		return DataObject::get_one(
			'BlogEntry', 
			"\"SiteTree\".\"ParentID\" = '$this->ParentID' AND \"BlogEntry\".\"Date\" < '$this->Date'", 
			true, 
			'Date DESC'
		);
	}
	
	/**
	 * Get the next blog entry from this section of blog pages.
	 *
	 * @return BlogEntry
	 */
	function NextBlogEntry() {
		return DataObject::get_one(
			'BlogEntry', 
			"\"SiteTree\".\"ParentID\" = '$this->ParentID' AND \"BlogEntry\".\"Date\" > '$this->Date'", 
			true, 
			'Date ASC'
		);		
	}

	/**
	 * Get the blog holder of this entry
	 *
	 * @return BlogHolder
	 */
	function getBlogHolder() {
		$holder = null; 
		if($this->ParentID && $this->Parent()->ClassName == 'BlogHolder') {
			$holder = $this->Parent(); 
		}

		return $holder;
	}

    function GetBackgroundImage(){
        if($this->BaseImageID){
            $file = DataObject::get_by_id('Image',$this->BaseImageID);

            //print_r($file->getHeight());die;
            return $file->Filename . ") no-repeat; height:" .$file->getHeight(). "px";
        }

        return ;

    }
}

class BlogEntry_Controller extends Page_Controller {
	
	static $allowed_actions = array(
		'index',
		'trackbackping',
		'unpublishPost',
		'PageComments',
		'SearchForm'
	);

	function init() {
		parent::init();
		
		Requirements::themedCSS("blog","blog");
	}
	
	/**
	 * Gets a link to unpublish the blog entry
	 */
	function unpublishPost() {
		if(!$this->IsOwner()) {
			Security::permissionFailure(
				$this,
				'Unpublishing blogs is an administrator task. Please log in.'
			);
		} else {
			$SQL_id = (int) $this->ID;
	
			$page = DataObject::get_by_id('SiteTree', $SQL_id);
			$page->deleteFromStage('Live');
			$page->flushCache();

			$this->redirect($this->getParent()->getParent()->Link());
		}		
	}


	/**
	 * Temporary workaround for compatibility with 'comments' module
	 * (has been extracted from sapphire/trunk in 12/2010).
	 * 
	 * @return Form
	 */
	function PageComments() {
		if($this->hasMethod('CommentsForm')) return $this->CommentsForm();
		else if(method_exists('Page_Controller', 'PageComments')) return parent::PageComments();
	}
		
}

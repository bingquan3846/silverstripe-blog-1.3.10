<?php

/**
 * @package blog
 */

/**
 * Blog tree is a way to group Blogs. It allows a tree of "Blog Holders".
 * Viewing branch nodes shows all blog entries from all blog holder children
 */

class BlogTree extends Page {

	static $icon = "sapphire/javascript/tree/images/page";

	static $description = "A grouping of blogs";

	static $singular_name = 'Blog Tree Page';

	static $plural_name = 'Blog Tree Pages';

	// Default number of blog entries to show
	static $default_entries_limit = 10;

	static $db = array(
		'LandingPageFreshness' => 'Varchar',
	);

	static $allowed_children = array(
		'BlogTree', 'BlogHolder'
	);



	/*
	 * Finds the BlogTree object most related to the current page.
	 * - If this page is a BlogTree, use that
	 * - If this page is a BlogEntry, use the parent Holder
	 * - Otherwise, try and find a 'top-level' BlogTree
	 *
	 * @param $page allows you to force a specific page, otherwise,
	 * 				uses current
	 */
	static function current($page = null) {

		if (!$page) {
			$controller = Controller::curr();
			if($controller) $page = $controller->data();
		}

		// If we _are_ a BlogTree, use us
		if ($page instanceof BlogTree) return $page;

		// Or, if we a a BlogEntry underneath a BlogTree, use our parent
		if($page->is_a("BlogEntry")) {
			$parent = $page->getParent();
			if($parent instanceof BlogTree) return $parent;
		}

		// Try to find a top-level BlogTree
		$top = DataObject::get_one('BlogTree', "\"ParentID\" = '0'");
		if($top) return $top;

		// Try to find any BlogTree that is not inside another BlogTree
		if($blogTrees=DataObject::get('BlogTree')) foreach($blogTrees as $tree) {
			if(!($tree->getParent() instanceof BlogTree)) return $tree;
		}

		// This shouldn't be possible, but assuming the above fails, just return anything you can get
		return $blogTrees->first();
	}

	/* ----------- ACCESSOR OVERRIDES -------------- */

	public function getLandingPageFreshness() {
		$freshness = $this->getField('LandingPageFreshness');
		// If we want to inherit freshness, try that first
		if ($freshness == "INHERIT" && $this->getParent()) $freshness = $this->getParent()->LandingPageFreshness;
		// If we don't have a parent, or the inherited result was still inherit, use default
		if ($freshness == "INHERIT") $freshness = '';
		return $freshness;
	}

	/* ----------- CMS CONTROL -------------- */

	function getSettingsFields() {
		$fields = parent::getSettingsFields();

		$fields->addFieldToTab(
			'Root.Settings',
			new DropdownField(
				'LandingPageFreshness',
				'When you first open the blog, how many entries should I show',
				array(
		 			"" => "All entries",
					"1" => "Last month's entries",
					"2" => "Last 2 months' entries",
					"3" => "Last 3 months' entries",
					"4" => "Last 4 months' entries",
					"5" => "Last 5 months' entries",
					"6" => "Last 6 months' entries",
					"7" => "Last 7 months' entries",
					"8" => "Last 8 months' entries",
					"9" => "Last 9 months' entries",
					"10" => "Last 10 months' entries",
					"11" => "Last 11 months' entries",
					"12" => "Last year's entries",
					"INHERIT" => "Take value from parent Blog Tree"
				)
			)
		);

		return $fields;
	}

	/* ----------- New accessors -------------- */

	public function loadDescendantBlogHolderIDListInto(&$idList) {
		if ($children = $this->AllChildren()) {
			foreach($children as $child) {
				if(in_array($child->ID, $idList)) continue;

				if($child instanceof BlogHolder) {
					$idList[] = $child->ID;
				} elseif($child instanceof BlogTree) {
					$child->loadDescendantBlogHolderIDListInto($idList);
				}
			}
		}
	}

	// Build a list of all IDs for BlogHolders that are children of us
	public function BlogHolderIDs() {
		$holderIDs = array();
		$this->loadDescendantBlogHolderIDListInto($holderIDs);
		return $holderIDs;
	}

	/**
	 * Get entries in this blog.
	 * @param string limit A clause to insert into the limit clause.
	 * @param string tag Only get blog entries with this tag
	 * @param string date Only get blog entries on this date - either a year, or a year-month eg '2008' or '2008-02'
	 * @param callback retrieveCallback A function to call with pagetype, filter and limit for custom blog sorting or filtering
	 * @param string $where
	 * @return DataObjectSet
	 */
	public function Entries($limit = '', $tag = '', $date = '', $retrieveCallback = null, $filter = '') {

		$tagCheck = '';
		$dateCheck = '';

		if($tag) {
			$SQL_tag = Convert::raw2sql($tag);
			$tagCheck = "AND \"BlogEntry\".\"Tags\" LIKE '%$SQL_tag%'";
		}

		if($date) {
			// Some systems still use the / seperator for date presentation
			if( strpos($date, '-') ) $seperator = '-';
			elseif( strpos($date, '/') ) $seperator = '/';

			if(isset($seperator) && !empty($seperator)) {
				// The 2 in the explode argument will tell it to only create 2 elements
				// i.e. in this instance the $year and $month fields respectively
				list($year,$month) = explode( $seperator, $date, 2);

				$year = (int)$year;
				$month = (int)$month;

				if($year && $month) {
					if(method_exists(DB::getConn(), 'formattedDatetimeClause')) {
						$db_date=DB::getConn()->formattedDatetimeClause('"BlogEntry"."Date"', '%m');
						$dateCheck = "AND CAST($db_date AS " . DB::getConn()->dbDataType('unsigned integer') . ") = $month AND " . DB::getConn()->formattedDatetimeClause('"BlogEntry"."Date"', '%Y') . " = '$year'";
					} else {
						$dateCheck = "AND MONTH(\"BlogEntry\".\"Date\") = '$month' AND YEAR(\"BlogEntry\".\"Date\") = '$year'";
					}
				}
			} else {
				$year = (int) $date;
				if($year) {
					if(method_exists(DB::getConn(), 'formattedDatetimeClause')) {
						$dateCheck = "AND " . DB::getConn()->formattedDatetimeClause('"BlogEntry"."Date"', '%Y') . " = '$year'";
					} else {
						$dateCheck = "AND YEAR(\"BlogEntry\".\"Date\") = '$year'";
					}
				}
			}
		}

		// Build a list of all IDs for BlogHolders that are children of us
		$holderIDs = $this->BlogHolderIDs();

		// If no BlogHolders, no BlogEntries. So return false
		if(empty($holderIDs)) return false;

		// Otherwise, do the actual query
		if($filter) $filter .= ' AND ';
		$filter .= '"SiteTree"."ParentID" IN (' . implode(',', $holderIDs) . ") $tagCheck $dateCheck";

		$order = '"BlogEntry"."Date" DESC ' ;

/*        if($limit !=  ''){
            $order = '"BlogEntry"."Date" DESC limit 0,' .$limit ;
        }*/

		// By specifying a callback, you can alter the SQL, or sort on something other than date.
		if($retrieveCallback) return call_user_func($retrieveCallback, 'BlogEntry', $filter, $limit, $order);

		$entries = BlogEntry::get('BlogEntry', $filter, $order);



        // get widget image from widgetimageID
        $count = count($entries->items);

        if($count){
            for($i=0;$i<$count;$i++){
                $icon = '';
                if($entries->items[$i]->record['widgetImageID']){
                    $file = DataObject::get_by_id('Image',(int)$entries->items[$i]->record['widgetImageID']);
                    $icon = $file->getTag();

                }
                if($entries->items[$i]->record['BaseImageID']){
                    $file = DataObject::get_by_id('Image',(int)$entries->items[$i]->record['BaseImageID']);
                    $detailImage = $file->getTag();
                }
                $entries->items[$i]->cover = $icon;
                $entries->items[$i]->detailImage = $detailImage;


            }
        }
        if($limit == -1){
            return $entries;
        }
        $perpage = 12;
        $blogs = new DataObjectSet($entries->items);
        if($blogs->count() <= $perpage){
            return $entries;
        }
        $blogs->setPageLength($perpage);
        $start = (isset($_GET['groupStart'])) ? (int) $_GET['groupStart'] : 1;
        $viewableBlogs = $blogs->getRange($start,$perpage);
        $viewableBlogs->setPaginationGetVar('groupStart');
        $viewableBlogs->setPageLimits($start,$perpage,$blogs->count());

/*         	$list = new PaginatedList($entries, Controller::curr()->request);
            $list->setPageLength($limit);
            return $list;*/
        //var_dump($viewableBlogs);var_dump($viewableBlogs->Pages());die;
        return $viewableBlogs;
	}
}

class BlogTree_Controller extends Page_Controller {

	static $allowed_actions = array(
		'index',
		'rss',
		'tag',
		'date'
	);
    static $popularities = array( 'not-popular', 'not-very-popular', 'somewhat-popular', 'popular', 'very-popular', 'ultra-popular' );
	function init() {
		parent::init();

		$this->IncludeBlogRSS();

		Requirements::themedCSS("blog","blog");
	}

	function BlogEntries($limit = null) {
		require_once('Zend/Date.php');

		if($limit === null) $limit = BlogTree::$default_entries_limit;
		// only use freshness if no action is present (might be displaying tags or rss)
		if ($this->LandingPageFreshness && !$this->request->param('Action')) {
			$d = new Zend_Date(SS_Datetime::now()->getValue());
			$d->sub($this->LandingPageFreshness, Zend_Date::MONTH);
			$date = $d->toString('YYYY-MM-dd');

			$filter = "\"BlogEntry\".\"Date\" > '$date'";
		} else {
			$filter = '';
		}
		// allow filtering by author field and some blogs have an authorID field which
		// may allow filtering by id
		if(isset($_GET['author']) && isset($_GET['authorID'])) {
			$author = Convert::raw2sql($_GET['author']);
			$id = Convert::raw2sql($_GET['authorID']);

			$filter .= " \"BlogEntry\".\"Author\" LIKE '". $author . "' OR \"BlogEntry\".\"AuthorID\" = '". $id ."'";
		}
		else if(isset($_GET['author'])) {
			$filter .=  " \"BlogEntry\".\"Author\" LIKE '". Convert::raw2sql($_GET['author']) . "'";
		}
		else if(isset($_GET['authorID'])) {
			$filter .=  " \"BlogEntry\".\"AuthorID\" = '". Convert::raw2sql($_GET['authorID']). "'";
		}

		$date = $this->SelectedDate();
        $container = BlogTree::current();

		return $container->Entries($limit, $this->SelectedTag(), ($date) ? $date : '', null, $filter);
	}

    function TagsCollection($limit = 0, $sortby = "alphabet"){
        $allTags = array();
        $max = 0;

        $container = BlogTree::current();

        $entries = $container->Entries(-1);

        if($entries) {
            foreach($entries as $entry) {
                $theseTags = preg_split(" *, *", mb_strtolower(trim($entry->Tags)));
                foreach($theseTags as $tag) {
                    if($tag != "") {
                        $tag = trim($tag);
                        $allTags[$tag] = isset($allTags[$tag]) ? $allTags[$tag] + 1 : 1; //getting the count into key => value map
                        $max = ($allTags[$tag] > $max) ? $allTags[$tag] : $max;
                    }
                }
            }

            if($allTags) {
                //TODO: move some or all of the sorts to the database for more efficiency
                if($limit > 0) $allTags = array_slice($allTags, 0, $limit, true);

                if($sortby == "alphabet"){
                    $this->natksort($allTags);
                } else{
                    uasort($allTags, array($this, "column_sort_by_popularity")); // sort by frequency
                }


                $sizes = array();
                foreach ($allTags as $tag => $count) $sizes[$count] = true;

                $offset = 0;
                $numsizes = count($sizes)-1; //Work out the number of different sizes
                $buckets = count(self::$popularities)-1;

                // If there are more frequencies than buckets, divide frequencies into buckets
                if ($numsizes > $buckets) {
                    $numsizes = $buckets;
                }
                // Otherwise center use central buckets
                else {
                    $offset   = round(($buckets-$numsizes)/2);
                }

                foreach($allTags as $tag => $count) {
                    $popularity = round($count / $max * $numsizes) + $offset; $popularity=min($buckets,$popularity);
                    $class = self::$popularities[$popularity];

                    $allTags[$tag] = array(
                        "Tag" => $tag,
                        "Count" => $count,
                        "Class" => $class,
                        "Link" => $container->Link('tag') . '/' . urlencode($tag)
                    );
                }
            }


            $output = array();

            $i = 1;
            $total = count($allTags);
            foreach($allTags as $tag => $fields) {
                if($i == $total){
                    $fields['IsLast'] = 1;

                }else{
                    $fields['IsLast'] = 0;
                }
                $i++;
                array_push($output, new ArrayData($fields));
            }
            //print_r($output);die;

            return (object)$output;
        }

       return;
    }

    private function column_sort_by_popularity($a, $b){
        if($a == $b) {
            $result  = 0;
        }
        else {
            $result = $b - $a;
        }
        return $result;
    }

    private function natksort(&$aToBeSorted) {
        $aResult = array();
        $aKeys = array_keys($aToBeSorted);
        natcasesort($aKeys);
        foreach ($aKeys as $sKey) {
            $aResult[$sKey] = $aToBeSorted[$sKey];
        }
        $aToBeSorted = $aResult;

        return true;
    }

    function archiveBlog( $displayMode = 'month'){
        $results = array();
        $container = BlogTree::current();
        $ids = $container->BlogHolderIDs();

        $stage = Versioned::current_stage();
        $suffix = (!$stage || $stage == 'Stage') ? "" : "_$stage";

        $monthclause = method_exists(DB::getConn(), 'formattedDatetimeClause') ? DB::getConn()->formattedDatetimeClause('Date', '%m') : 'MONTH(Date)';
        $yearclause  = method_exists(DB::getConn(), 'formattedDatetimeClause') ? DB::getConn()->formattedDatetimeClause('Date', '%Y') : 'YEAR(Date)';

        if($displayMode == 'month') {
            $sqlResults = DB::query("
					SELECT DISTINCT CAST($monthclause AS " . DB::getConn()->dbDataType('unsigned integer') . ") AS Month, $yearclause AS Year
					FROM SiteTree$suffix INNER JOIN BlogEntry$suffix ON SiteTree$suffix.ID = BlogEntry$suffix.ID
					WHERE ParentID IN (" . implode(', ', $ids) . ")
					ORDER BY Year DESC, Month DESC;"
            );
        } else {
            $sqlResults = DB::query("
					SELECT DISTINCT $yearclause AS \"Year\"
					FROM \"SiteTree$suffix\" INNER JOIN \"BlogEntry$suffix\" ON \"SiteTree$suffix\".\"ID\" = \"BlogEntry$suffix\".\"ID\"
					WHERE \"ParentID\" IN (" . implode(', ', $ids) . ")
					ORDER BY \"Year\" DESC"
            );
        }


        if($sqlResults) foreach($sqlResults as $sqlResult) {
            $isMonthDisplay = $displayMode == 'month';

            $monthVal = (isset($sqlResult['Month'])) ? (int) $sqlResult['Month'] : 1;
            $month = ($isMonthDisplay) ? $monthVal : 1;
            $year = ($sqlResult['Year']) ? (int) $sqlResult['Year'] : date('Y');

            $date = DBField::create('Date', array(
                'Day' => 1,
                'Month' => $month,
                'Year' => $year
            ));


            if($isMonthDisplay) {
                $link = $container->Link('date') . '/' . $sqlResult['Year'] . '/' . sprintf("%'02d", $monthVal);
            } else {
                $link = $container->Link('date') . '/' . $sqlResult['Year'];
            }
/*            print_r(  "SELECT count(BlogEntry$suffix.ID) FROM SiteTree$suffix INNER JOIN BlogEntry$suffix ON SiteTree$suffix.ID = BlogEntry$suffix.ID  and  BlogEntry$suffix.date >='" . $year . "-" . sprintf("%02s", $month) . "-01'"." and BlogEntry$suffix.date <='" . $year . "-" . sprintf("%02s", $month) . "-31'"."
				                WHERE ParentID IN (" . implode(', ', $ids) . ") ");die;*/


            $sqlcount = DB::query(
                                "SELECT count(BlogEntry$suffix.ID) FROM SiteTree$suffix INNER JOIN BlogEntry$suffix ON SiteTree$suffix.ID = BlogEntry$suffix.ID  and  BlogEntry$suffix.date >='" . $year . "-" . sprintf("%02s", $month) . "-01'"." and BlogEntry$suffix.date <='" . $year . "-" . sprintf("%02s", $month) . "-31'"."
				                WHERE ParentID IN (" . implode(', ', $ids) . ") ");
            $objCount = $sqlcount->current();

            array_push($results,new ArrayData(array(
                'Date' => $date,
                'Link' => $link,
                'Count'=> $objCount['count(BlogEntry_Live.ID)']
            )));
        }

        return (object)$results;
    }
	/**
	 * This will create a <link> tag point to the RSS feed
	 */
	function IncludeBlogRSS() {
		RSSFeed::linkToFeed($this->Link('rss'), _t('BlogHolder.RSSFEED',"RSS feed of these blogs"));
	}

	/**
	 * Get the rss feed for this blog holder's entries
	 */
	function rss() {
		global $project_name;

		$blogName = $this->Title;
		$altBlogName = $project_name . ' blog';

		$entries = $this->Entries(20);

		if($entries) {
			$rss = new RSSFeed($entries, $this->Link('rss'), ($blogName ? $blogName : $altBlogName), "", "Title", "RSSContent");
			return $rss->outputToBrowser();
		}
	}

	/**
	 * Protection against infinite loops when an RSS widget pointing to this page is added to this page
	 */
	function defaultAction($action) {
		if(stristr($_SERVER['HTTP_USER_AGENT'], 'SimplePie')) return $this->rss();

		return parent::defaultAction($action);
	}

	/**
	 * Return the currently viewing tag used in the template as $Tag
	 *
	 * @return String
	 */
	function SelectedTag() {
		return ($this->request->latestParam('Action') == 'tag') ? Convert::raw2xml($this->request->latestParam('ID')) : '';
	}

	/**
	 * Return the selected date from the blog tree
	 *
	 * @return Date
	 */
	function SelectedDate() {
		if($this->request->latestParam('Action') == 'date') {
			$year = $this->request->latestParam('ID');
			$month = $this->request->latestParam('OtherID');

			if(is_numeric($year) && is_numeric($month) && $month < 13) {

				$date = $year .'-'. $month;
				return $date;

			} else {

				if(is_numeric($year)) return $year;
			}
		}

		return false;
	}

	/**
	 * @return String
	 */
	function SelectedAuthor() {
		if($this->request->getVar('author')) {
			$hasAuthor = BlogEntry::get()->filter('Author', $this->request->getVar('author'))->Count();
			return $hasAuthor ? Convert::raw2xml($this->request->getVar('author')) : null;
		} elseif($this->request->getVar('authorID')) {
			$hasAuthor = BlogEntry::get()->filter('AuthorID', $this->request->getVar('authorID'))->Count();
			if($hasAuthor) {
				$member = Member::get()->byId($this->request->getVar('authorID'));
				if($member) {
					if($member->hasMethod('BlogAuthorTitle')) {
						return Convert::raw2xml($member->BlogAuthorTitle);
					} else {
						return Convert::raw2xml($member->Title);
					}
				} else {
					return null;
				}
			}
		}
	}

	function SelectedNiceDate(){
		$date = $this->SelectedDate();

		if(strpos($date, '-')) {
			$date = explode("-",$date);
			return date("F", mktime(0, 0, 0, $date[1], 1, date('Y'))). " " .date("Y", mktime(0, 0, 0, date('m'), 1, $date[0]));

		} else {
			return date("Y", mktime(0, 0, 0, date('m'), 1, $date));
		}
	}
}

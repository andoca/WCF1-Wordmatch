<?php
require_once (WCF_DIR . 'lib/system/event/EventListener.class.php');

/**
 * EventListener for the WordMatch Plugin.
 * Easy search&replace function
 *
 * @package de.0xdefec.wordmatch
 * @author Andreas Diendorfer
 * @copyright 12.01.2008 - Andreas Diendorfer
 */

class WordMatchListener implements EventListener {

	/**
	 *
	 * @see EventListener::execute()
	 * @param $eventObj IndexPage       	
	 */
	
	private $wordmatch = array ();

	private $search = array ();

	private $replace = array ();

	public function execute($eventObj, $className, $eventName) {
		$this->wordmatch = ArrayUtil::trim(explode("\n", trim(WORDMATCH_LIST)));
		
		$bbCode = false;
		$noLink = false;
		switch ($className) {
			case 'UserGuestbookPage':
			case 'UserBlogPage':
				$bbCode = true;
			break;
			case 'UserBlogEntryPage':
				$bbCode = true;
			break;
			case 'UserBlogFeedPage':
				$bbCode = true;
			break;
			case 'PostsFeedPage':
			case 'UserBlogOverviewPage':
				$bbCode = true;
			break;
			case 'PMViewPage':
				$bbCode = true;
			break;
			case 'UserGalleryPhotoPage':
				$noLink = true;
			break;
		}
		
		foreach ($this->wordmatch as $entry) {
			if (!preg_match("/^.*\;.*$/i", $entry))
				continue; // wrong format in this line
			list ( $search_str, $replace_str ) = explode(';', $entry, 2); // only
			                                                              // explode at
			                                                              // first
			                                                              // matching
			                                                              // character
			if (substr($replace_str, 0, 4) == 'url:') {
				if ($noLink) {
					$replace_str = '$1';
				} else {
					if (!$bbCode) {
						if (WORDMATCH_NEW_WINDOW)
							$blank = ' target="_blank"';
						else
							$blank = '';
						$replace_str = '<a href="' . str_replace('url:', '', $replace_str) . '" ' . $blank . ' class="wordmatchLink">$1</a>';
					} else
						$replace_str = '[url=' . str_replace('url:', '', $replace_str) . ']$1[/url]';
				}
			}
			
			$this->search [] = '/(?!<.*?)(?!<a)((?<!\p{L})(' . $search_str . ')(?!\p{L}))(?!<\/a>)(?![^<>]*?>)/iu';
			$this->replace [] = $replace_str;
		}
		
		switch ($className) {
			case 'PostsFeedPage':
				if (WORDMATCH_WBB_FEED) {
					foreach ($eventObj->posts as &$post) {
						$post->message = $this->replaceString($post->message);
					}
				}
			break;
			case 'UserGuestbookPage':
				if (WORDMATCH_GUESTBOOK) {
					$entries = $eventObj->entryList->getObjects();
					foreach ($entries as &$entry) {
						$entry->message = $this->replaceString($entry->message);
					}
				}
			break;
			case 'PMViewPage':
				if (WORDMATCH_PM) {
					$pms = $eventObj->pmList->getObjects();
					foreach ($pms as &$pm) {
						$pm->message = $this->replaceBBCodeString($pm->message);
					}
				}
			break;
			case 'UserBlogPage':
				if (WORDMATCH_BLOG) {
					$entries = $eventObj->entryList->getObjects();
					foreach ($entries as &$entry) {
						$entry->message = $this->replaceBBCodeString($entry->message);
					}
				}
			break;
			case 'UserBlogOverviewPage':
				if (WORDMATCH_BLOG) {
					$entries = $eventObj->entryList->getObjects();
					foreach ($entries as &$entry) {
						$entry->message = $this->replaceBBCodeString($entry->message);
					}
				}
			break;
			case 'UserBlogFeedPage':
				if (WORDMATCH_BLOG_FEED) {
					$entries = $eventObj->entryList->getObjects();
					foreach ($entries as &$entry) {
						$entry->message = $this->replaceBBCodeString($entry->message);
					}
				}
			break;
			case 'UserBlogEntryPage':
				if (WORDMATCH_BLOG) {
					$eventObj->entry->message = $this->replaceBBCodeString($eventObj->entry->message);
				}
			break;
			case 'UserGalleryPhotoPage':
				if (WORDMATCH_GALLERY) {
					$eventObj->photo->description = $this->replaceBBCodeString($eventObj->photo->description);
					$comments = $eventObj->commentList->getObjects();
					if (WORDMATCH_GALLERY_COMMENTS) {
						foreach ($comments as &$comment) {
							$comment->comment = $this->replaceBBCodeString($comment->comment);
						}
					}
				}
			break;
			case 'ThreadPage':
				if (WORDMATCH_WBB) {
					foreach ($eventObj->postList->posts as &$post) {
						if ($post->messageCache) {
							$post->messageCache = $this->replaceString($post->messageCache);
						} else {
							$post->messageCache = $this->replaceString($post->getFormattedMessage());
						}
					}
				}
			break;
		}
	}

	private function processHTMLDom($node, $dom) {
		if ($node->hasChildNodes()) {
			$nodes = array ();
			foreach ($node->childNodes as $childNode) {
				$nodes [] = $childNode;
			}
			
			foreach ($nodes as $childNode) {
				if ($childNode instanceof DOMText && $childNode->parentNode->localName != "a") {
					$text = preg_replace($this->search, $this->replace, $childNode->wholeText);
					$newNode = $dom->createDocumentFragment();
					$newNode->appendXML($text);
					$node->replaceChild($newNode, $childNode);
				} else {
					$this->processHTMLDom($childNode, $dom);
				}
			}
		}
	}

	private function replaceString($str) {
		try {
			$doc = new DOMDocument();
			$doc->loadHtml('<?xml encoding="UTF-8">' . $str);
			
			foreach ($doc->childNodes as $item) {
				if ($item->nodeType == XML_PI_NODE) {
					$doc->removeChild($item); // remove hack
				}
			}
			$doc->encoding = 'UTF-8'; // insert proper
			
			$this->processHTMLDom($doc, $doc);
			$str = $doc->saveHTML();
			$str = preg_replace('/^<!DOCTYPE.+?>/', '', str_replace(array (
					'<html>', 
					'</html>', 
					'<body>', 
					'</body>' 
			), array (
					'', 
					'', 
					'', 
					'' 
			), $doc->saveHTML()));
		} catch (Exception $e) {}
		return $str;
	}

	private function replaceBBCodeString($str) {
		return preg_replace($this->search, $this->replace, $str);
	}

	private function parse_glossar_array($parse_arr) {
		if (strpos($parse_arr, "<") === false) {
			return (preg_replace($this->search, $this->replace, $parse_arr));
		} else {
			return ($parse_arr);
		}
	}

}

?>
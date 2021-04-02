<?php

class CrawlerResult {

  private $url;
  private $uniqueExternalLinks;
  private $uniqueInternalLinks;
  private $uniqueImages;
  private $wordCount;
  private $statusCode;
  private $titleLength;
  private $loadTime;

  public function __construct($pageUrl, $images, $externalLinks, $internalLinks, $loadTime, $statusCode, $title, $words) {
    $this->url = $pageUrl;
    $this->uniqueExternalLinks = $externalLinks;
    $this->uniqueInternalLinks = $internalLinks;
    $this->uniqueImages = $images;
    $this->wordCount = $words;
    $this->statusCode = $statusCode;
    $this->loadTime = $loadTime;
    $this->titleLength = $title;
  }

  public function setUniqueImage($image) {
    $this->uniqueImages = $image;
  }

  public function setWordCounts($words) {
    $this->wordCount = $words;
  }

  public function setStatusCode($code) {
    $this->statusCode = $code;
  }

  public function setLoadTime($time) {
    $this->loadTime = $time;
  }

  public function getExternalLinks() {
    return $this->uniqueExternalLinks;
  }

  public function __toString(): string {
    $countExternalLinks = is_array($this->uniqueExternalLinks) ? count($this->uniqueExternalLinks) : 0;
    $countInternalLinks = is_array($this->uniqueInternalLinks) ? count($this->uniqueInternalLinks) : 0;
    $countImages = is_array($this->uniqueImages) ? count($this->uniqueImages) : 0;
    return "Page: $this->url<br>"
            ."Unique External Links: $countExternalLinks<br>"
            ."Unique Internal Links: $countInternalLinks<br>"
            ."Unique Images: $countImages<br>"
            ."Total Word Count: $this->wordCount<br>"
            ."Load Time: $this->loadTime<br>"
            ."HTTP Status: $this->statusCode<br>"
            ."Title: $this->titleLength<br>"
            ."--------------------------------------------------<br>";
  }
}


class Crawler
{
  protected $_url;
  protected $_depth;
  protected $_host;
  protected $_useHttpAuth = false;
  protected $_seen = array();
  protected $_filter = array();
  private $uniqueImages;

  private $unique_internal_links;
  private $unique_external_links;

  private $counter;


  const BASE_URL = 'agencyanalytics.com';

  private $result;

  public function __construct($url, $depth = 5)
  {
    $this->_url = $url;
    $this->_depth = $depth;
    $parse = parse_url($url);
    $this->_host = $parse['host'];
    $this->unique_internal_links = [];
    $this->unique_external_links = [];
    $this->uniqueImages = [];
    $this->result = [];
    $this->counter = 0;
  }

  private function isExternalLink (string $link): bool {
    return (strpos($link, self::BASE_URL) === false && strpos($link, 'http') !== false) ;
  }

  private function processImages($content) {
    $dom = new DOMDocument('1.0');
    @$dom->loadHTML($content);
    $imagesOnPage = $dom->getElementsByTagName('img');
    $srcCounter = 0;

    $images = [];
    $uniqueImages = [];
    foreach ($imagesOnPage as $image) {
      $images[] = $image->getAttribute('src');
      $srcCounter++;
    }
    $imageSummary = array_count_values($images);
    foreach ($imageSummary as $image=>$count) {
      if ($count === 1) {
        $uniqueImages[] = $image;
      }
    }
    //echo "<br>Total Image: ". $srcCounter . "Unique images: " .count($uniqueImages). "<br>";

    return $uniqueImages;
  }

  private function countWordsInPage($url) {
    $pageText = @file_get_contents($url);
    $search = array('@<script[^>]*?>.*?</script>@si',  // Strip out javascript
//      '@<head>.*?</head>@siU',            // Lose the head section
      '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
      '@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments including CDATA
    );

    $contents = preg_replace($search, '', $pageText);
    $total = str_word_count(strip_tags($contents));

    echo "<br>Total Words: ". $total. "<br>";
    return $total;
  }

  private function getTitle($content) {
    $dom = new DOMDocument('1.0');
    @$dom->loadHTML($content);
    $anchors = $dom->getElementsByTagName('title');
    $title = $anchors->item(0)->nodeValue;
    return $title;
  }

  protected function processLink($content, $url, $depth, $httpcode, $time)
  {
    $dom = new DOMDocument('1.0');
    @$dom->loadHTML($content);
    $anchors = $dom->getElementsByTagName('a');
    $links = [];
    $uniqueImages = $this->processImages($content);
    $wordsInPage = $this->countWordsInPage($url);
    $title = $this->getTitle($content);
    foreach ($anchors as $a) {
      $links[] = $a->getAttribute('href');
    }
    $linkSummary = array_count_values($links);
    $externalLinks = [];
    $internalLinks = [];
    foreach ($linkSummary as $link => $count) {
      if ($count === 1) {
        if ($this->isExternalLink($link)) {
          //$this->unique_external_links[] = $link;
          $externalLinks[] = $link;
        }
        else {
          //$this->unique_internal_links[] = $link;
          $internalLinks[] = $link;
        }
      }
    }

    $this->result[] = new CrawlerResult(
      $url,
      $uniqueImages,
      $externalLinks,
      $internalLinks,
      $time,
      $httpcode,
      $title,
      $wordsInPage
    );

//    $this->result[] = new CrawlerResult(
//      $url,
//      $uniqueImages,
//      count($this->unique_external_links),
//      count($this->unique_internal_links),
//      $time,
//      $httpcode,
//      $title,
//      $wordsInpage
//    );

    foreach ($anchors as $element) {
      $href = $element->getAttribute('href');
      if (0 !== strpos($href, 'http')) {
        $path = '/' . ltrim($href, '/');
        if (extension_loaded('http')) {
          $href = http_build_url($url, array('path' => $path));
        } else {
          $parts = parse_url($url);
          $href = $parts['scheme'] . '://';
          if (isset($parts['user']) && isset($parts['pass'])) {
            $href .= $parts['user'] . ':' . $parts['pass'] . '@';
          }
          $href .= $parts['host'];
          if (isset($parts['port'])) {
            $href .= ':' . $parts['port'];
          }
          $href .= $path;
          //echo "<br>$href";
        }
      }
      // Crawl only link that belongs to the start domain
      $this->crawl_page($href, $depth - 1);
    }
  }

  protected function _getContent($url)
  {
    $handle = curl_init($url);
    if ($this->_useHttpAuth) {
      curl_setopt($handle, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
      curl_setopt($handle, CURLOPT_USERPWD, $this->_user . ":" . $this->_pass);
    }
    // follows 302 redirect, creates problem wiht authentication
//        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, TRUE);
    // return the content
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);

    /* Get the HTML or whatever is linked in $url. */
    $response = curl_exec($handle);
    // response total time
    $time = curl_getinfo($handle, CURLINFO_TOTAL_TIME);
    /* Check for 404 (file not found). */
    $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

    curl_close($handle);
    return array($response, $httpCode, $time);
  }

  protected function _printResult($url, $depth, $httpcode, $time)
  {
    ob_end_flush();
    $currentDepth = $this->_depth - $depth;
    $count = count($this->_seen);
    $this->counter++;
    echo "N::$count,CODE::$httpcode,TIME::$time,DEPTH::$currentDepth URL::$url <br>";
    //$this->_depth = $currentDepth;
    ob_start();
    flush();
  }

  protected function isValid($url, $depth)
  {
    if (strpos($url, $this->_host) === false
      || $depth === 0
      || isset($this->_seen[$url])
    ) {
      return false;
    }
    foreach ($this->_filter as $excludePath) {
      if (strpos($url, $excludePath) !== false) {
        return false;
      }
    }
    return true;
  }

  public function crawl_page($url, $depth)
  {
    if (!$this->isValid($url, $depth)) {
      return;
    }
    // add to the seen URL
    $this->_seen[$url] = true;
    // get Content and Return Code
    list($content, $httpcode, $time) = $this->_getContent($url);
    // print Result for current Page
    $this->_printResult($url, $depth, $httpcode, $time);
    // process subPages
    $this->processLink($content, $url, $depth, $httpcode, $time);
  }

  public function setHttpAuth($user, $pass)
  {
    $this->_useHttpAuth = true;
    $this->_user = $user;
    $this->_pass = $pass;
  }

  public function addFilterPath($path)
  {
    $this->_filter[] = $path;
  }

  public function run()
  {
    $this->crawl_page($this->_url, $this->_depth);
    echo "<br>The End<br>";

//    foreach(Crawler::$linksToVisit as $link) {
//      echo "<br>$link<br>";
//    }

    $i = 0;
    foreach ($this->result as $result) {
      echo ++$i . ": ". $result;
      //print_r($result->getExternalLinks());
    }



  }

}
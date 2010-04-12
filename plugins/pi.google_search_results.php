<?php

/*
	Copyright 2010 Derek Hogue
	
	This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
*/

$plugin_info = array(
	'pi_name'        => 'Google Search Results',
	'pi_version'     => '1.0.1',
	'pi_author'      => 'Derek Hogue',
	'pi_author_url'  => 'http://amphibian.info',
	'pi_description' => 'Display Google search results in your EE templates using the Google AJAX Search API.',
	'pi_usage'       => Google_search_results::usage()
);

class Google_search_results
{

	var $return_data = "";
	
	function Google_search_results()
	{
		global $FNS, $IN, $OUT, $TMPL;
						
		// Fetch search terms from GET or POST
		$terms = ($IN->GBL('q')) ? $IN->GBL('q') : FALSE;
			
		if($terms === FALSE)
		{
			// No terms = no search
			$OUT->show_user_error('general', "No search terms were specified.");
		}
	
		// Arguments for the API call
		$args = array();
		
		// Search only this site
		$site = ($TMPL->fetch_param('site') !== FALSE) ? $TMPL->fetch_param('site') : FALSE;
		
		// Google Custom Search Engine ID
		if($TMPL->fetch_param('cse') != FALSE) $args['cx'] = $TMPL->fetch_param('cse');

		// Wear a condom
		$args['safe'] = ($TMPL->fetch_param('safe') !== FALSE && 
			in_array($TMPL->fetch_param('safe'), array('active', 'moderate', 'off'))) ?
			$TMPL->fetch_param('safe') : 'off';	
		
		// Watch your language
		$args['lr'] = ($TMPL->fetch_param('language') !== FALSE) ? $TMPL->fetch_param('language') : 'en';	

		// No dupes
		$args['filter'] = ($TMPL->fetch_param('filter') !== FALSE && $TMPL->fetch_param('filter') == '0') ? '0' : '1';
		
		// Home on native land
		$args['gl'] = ($TMPL->fetch_param('gl') !== FALSE) ? $TMPL->fetch_param('gl') : 'us';		
		
		// Ready the query
		$q = (isset($site)) ? urlencode('site:'.$site.' '.$terms) : urlencode($terms);
		
		// Determine offset
		$page = ($IN->GBL('p')) ? $IN->GBL('p') : 1;
		// The API only allows a max of 64 total results, with 8 results per response
		if($page > 8) $page = 8;
		$start = ($page - 1) * 8;
		
		// Compile args
		$arg_string = '';
		foreach($args as $k => $v)
		{
			$arg_string .= $k . '=' . $v . '&';
		}
		
		// Build the URL!
		$url = 'http://ajax.googleapis.com/ajax/services/search/web?v=1.0&userip='.$IN->IP.'&rsz=large&'.$arg_string.'q='.$q.'&start='.$start;
		
		// Send the request
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_REFERER, $_SERVER['HTTP_REFERER']);
		$results = curl_exec($ch);
		curl_close($ch);
	
		if($results) // There's a voice on the hotline
		{
			
			if(! class_exists('Typography'))
			{
		 		require_once PATH_CORE.'core.typography'.EXT;
		 	}
		 	$format = new Typography;
			
			// Backwards-compatibility for PHP < 5.2
			if( !function_exists('json_decode') && file_exists(PATH_LIB.'/JSON.php'))
			{
			    function json_decode($content, $assoc=false){
			    	require_once PATH_LIB.'/JSON.php';
			        $json = new Services_JSON;
			        return $json->decode($content);
			    }
			}
	
			if(function_exists('json_decode'))
			{
				$json = json_decode($results);
				
				$conds = array();
				
				// Estimated total results indexed by Google
				$conds['total_google_search_results'] = $json->responseData->cursor->estimatedResultCount;
				
				// Total search results we're able to display for this search via the API (can't be more than 64)
				$conds['results_overflow'] = ($conds['total_google_search_results'] > 64) ? TRUE : FALSE;
				
				// If we have more than 64 results indexed, declare only 64 results for this search
				$conds['total_search_results'] = ($conds['results_overflow'] == TRUE) ? 64 : $conds['total_google_search_results'];
				
				// Total number of pages for this search
				$conds['total_pages'] = count($json->responseData->cursor->pages);
				
				// Total results for this specific page of results
				$conds['total_page_results'] = count($json->responseData->results);
				
				$entries_list = '';
				
				if($conds['total_page_results'] == 0)
				{
					$conds['no_search_results'] = TRUE;
				}
				else
				{
					$conds['search_results'] = TRUE;
					
					// {results} loop
					
					// Are we removing some of the title attribute
					$title = ($TMPL->fetch_param('remove_title') !== FALSE) ? $TMPL->fetch_param('remove_title') : '';
					
					// Fetch contents of the results tag			
					$entries = $TMPL->fetch_data_between_var_pairs($TMPL->tagdata, 'results');
					
					$conds['count'] = 1;
					foreach($json->responseData->results as $result)
					{
						$entry = $FNS->prep_conditionals($entries, $conds);
						$entry = $TMPL->swap_var_single('title', str_replace($title, '', $result->titleNoFormatting), $entry);
						$entry = $TMPL->swap_var_single('url', $this->clean_url($result->unescapedUrl), $entry);
						$entry = $TMPL->swap_var_single('cached_url', $this->clean_url($result->cacheUrl), $entry);
						$entry = $TMPL->swap_var_single('excerpt', $result->content, $entry);
						$entry = $TMPL->swap_var_single('count', $conds['count'], $entry);
						$entries_list .= $entry;
						$conds['count']++;
					}
					
					// Build pagination
					$pagination = '';
					if($conds['total_pages'] > 1)
					{
						$conds['paginate'] = TRUE;
						$next = ($TMPL->fetch_param('next_page') !== FALSE) ? $TMPL->fetch_param('next_page') : '&raquo;';
						$prev = ($TMPL->fetch_param('prev_page') !== FALSE) ? $TMPL->fetch_param('prev_page') : '&laquo;';
						
						if($page > '1')
						{
							$pagination .= '<a class="search-result-page prev-page" href="'.$this->base_url().'q='.urlencode($terms).'&amp;p='.($page-1).'">'.$prev.'</a> ';
						}						
						
						foreach($json->responseData->cursor->pages as $p)
						{
							
							if($p->label == $page)
							{
								$pagination .= '<strong class="search-result-page current-result-page">'.$p->label.'</strong> ';
							}
							else
							{
								$pagination .= '<a class="search-result-page" href="'.$this->base_url().'q='.urlencode($terms).'&amp;p='.$p->label.'">'.$p->label.'</a> ';
							}
						}
						
						if($page < $conds['total_pages'])
						{
							$pagination .= '<a class="search-result-page next-page" href="'.$this->base_url().'q='.urlencode($terms).'&amp;p='.($page+1).'">'.$next.'</a> ';
						}
					}
					
					if($page >= $conds['total_pages']) $conds['last_results_page'] = TRUE;					
				}
				
				$tagdata = $FNS->prep_conditionals($TMPL->tagdata, $conds);
				$tagdata = $TMPL->swap_var_single('keywords', $format->light_xhtml_typography(stripslashes($terms)) , $tagdata);
				$tagdata = $TMPL->swap_var_single('total_page_results', $conds['total_page_results'] , $tagdata);
				$tagdata = $TMPL->swap_var_single('total_search_results', $conds['total_search_results'] , $tagdata);
				$tagdata = $TMPL->swap_var_single('total_pages', $conds['total_pages'], $tagdata);
				$tagdata = $TMPL->swap_var_single('page_number', $page, $tagdata);
				$tagdata = $TMPL->swap_var_single('total_google_search_results', $conds['total_google_search_results'] , $tagdata);
				$tagdata = $TMPL->swap_var_single('google_results_url', $this->clean_url($json->responseData->cursor->moreResultsUrl), $tagdata);
				$tagdata = $TMPL->swap_var_single('pagination', $pagination, $tagdata);
				$tagdata = preg_replace("/".LD.preg_quote('results').RD.".*?".LD.SLASH.'results'.RD."/s", $entries_list, $tagdata);
		
				$this->return_data = $tagdata;
			}
		}
		else
		{
			// No response from the cURL request - maybe no cURL support? Who knows. Who cares!
			$OUT->show_user_error('general', "Sorry, but your search could not be completed.");
		}
	
	}

   
	function keywords()
	{
		global $IN;
		if(! class_exists('Typography'))
		{
	 		require_once PATH_CORE.'core.typography'.EXT;
	 	}
	 	$format = new Typography;		
		return ($IN->GBL('q')) ? $format->light_xhtml_typography(stripslashes($IN->GBL('q'))) : FALSE;
	}
	

	function clean_url($url)
	{
		return preg_replace('/&([^#])(?![a-z]{2,8};)/', '&amp;$1', $url);
	}

   
	function base_url()
	{
		global $FNS, $TMPL;
		
		// Compatibility mode for PHP as CGI with .htaccess rules that use RewriteRule ^(.*)$ /index.php?/$1
		// (Necessitates removal of the query indicator in the URL.)
		// You'll probably need your search form to use POST if this is the case as well
		// (as GET requests will automatically add a query indicator).
		
		if($TMPL->fetch_param('remove_query_indicator') == 'y')
		{
			return preg_replace('/&[.]*$/', '', $FNS->fetch_current_uri()) . '&amp;';
		}
		else
		{
			return preg_replace('/\?[.]*$/', '', $FNS->fetch_current_uri()) . '?';
		}	
	}
	

	function usage()
	{
  		ob_start(); 
	?>

This plugins leverages the Google AJAX Search API to retreive search results for your site (or from your Google Custom Search Engine) and display them using good ole' EE templates.

NOTE: Currently the Google AJAX Search API limits queries to 8 results per page, with a total of 64 results for any given query.

Simply create a search form with a text input named 'q' and submit it via POST or GET to a template on your site containing the Google Search Results tag pair.

You can also display the search keywords outside of the tag pair using {exp:google_search_results:keywords}.

PARAMETERS:

country="us" -- ISO-3166-1 country code to tailor results to.  Default is "us".
cse="" -- ID of your Google Custom Search Engine, if you have one setup for your site (i.e. 000455696194071821846:reviews).
filter="1" -- duplicate content filter setting, either "1" (on, default) or "0" (off).
language="en" -- language code to restrict the results to (defaults to "en").
next_page="&raquo;" -- character or entity to use as the "Next Page" link (defaults to &raquo;)
prev_page="&laquo;" -- character or entity to use as the "Previous Page" link (defaults to &laquo;)
remove_query_indicator="y" -- use this if you're finding that the keyword and pagination arguments aren't registering when searching (you always get "no keywords" error). This is likely due to a combination of using PHP as CGI and using a query indicator (?) in your .htaccess ReWrite rule that removes index.php. Note that this will also mean that you'll need to use the POST method on your search form.
remove_title=" | Name of Your Site" -- text string to remove from the title of your search results (e.g. your site name, preceeded or appended by a pipe or colon).
safe="off" -- level of safe search filtering ("high", "moderate", or "off" (default). (Yes, I am a sick, perverted bastard.)
site="yoursite.com" -- domain to restrict searches to. (Leave out to get generalized Google search results.)

VARIABLES:

{results}{/results} -- the main search results tag pair. Within this loop you can use the following variables:

{title} -- the page title of the search result
{url} -- the URL of the search result
{excerpt} -- Google's excerpt from the search result, with keywords emphasized
{cached_url} --  link to Google's cached copy of the search result

You can also use both {count} and {total_page_results} tags within the loop, as variables or conditionals.

Outside the {results} loop you may use the following tags:

{keywords} -- the keywords you searched for
{total_search_results} -- total number of results returned via the API
{total_google_search_results} -- total number of results available via Google for your search
{page_number} -- current page number (1 through 8) of the results
{total_pages} -- total number of pages of results for your query
{google_results_url} -- URL to your search on Google

You also have access to the following conditionals:

{if paginate} -- TRUE if there is more than one page of results returned
{if search_results} -- TRUE if there are more than 0 results for your search
{if no_search_results} -- TRUE if there are no results from your search
{if results_overflow} -- TRUE if the total number of available results via Google exceeds the API's limt of 64
{if last_results_page} -- TRUE if you're on the last page of your search results


EXAMPLE:

{exp:google_search_results site="yoursite.com"}
		
	{if search_results}
		<p>You searched for <strong>{keywords}</strong> and got {total_search_results} {if total_search_results == "1"}result{if:else}results{/if}.</p>
	{/if}
		
	{results}
	
		{if count == "1"}
			<ul>
		{/if}
		
		<li>
			<h3><a href="{url}">{title}</a></h3>
			<p>{excerpt}</p>
			<p><small>{url} <a href="{cached_url}">(cached)</a></small></p>
		</li>
		
		{if count == total_page_results}
			</ul>
		{/if}
		
	{/results}
		
	{if no_search_results}
		<p>Sorry, no results for <strong>{keywords}</strong>.</p>
	{/if}
	
	{if paginate}
		<p>Page {page_number} of {total_pages} : {pagination}</p>
	{/if}
	
	{if last_results_page && results_overflow}
	<p><a href="{google_results_url}" target="_blank">View all {total_google_search_results} results on Google</a></p>
	{/if}
		
{/exp:google_search_results}

--

Compatibility note: if you're running PHP < 5.2, you'll need to upload the included JSON.php file to your /system/lib/ directory. Also, cURL support!

<?php
      $buffer = ob_get_contents();

      ob_end_clean(); 

      return $buffer;
   }

}

// Every step a fucking adventure.
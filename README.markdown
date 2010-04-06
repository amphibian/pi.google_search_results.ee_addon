This plugins leverages the [Google AJAX Search API](http://code.google.com/apis/ajaxsearch/) to retreive search results for your site (or from your [Google Custom Search Engine](http://www.google.com/cse/)) and display them using good ole' EE templates.

*NOTE:* Currently the Google AJAX Search API limits queries to 8 results per page, with a total of 64 results for any given query.

##Usage

Simply create a search form with a text input named 'q' and submit it via POST or GET to a template on your site containing the Google Search Results tag pair.

-----------------------

#Parameters

- `country="us"` -- ISO-3166-1 country code to tailor results to.  Default is "us".
- `cse=""` -- ID of your Google Custom Search Engine, if you have one setup for your site (i.e. 000455696194071821846:reviews).
- `filter="1"` -- duplicate content filter setting, either "1" (on, default) or "0" (off).
- `language="en"` -- language code to restrict the results to (defaults to "en").
- `next_page="&raquo;"` -- character or entity to use as the "Next Page" link (defaults to &raquo;)
- `prev_page="&laquo;"` -- character or entity to use as the "Previous Page" link (defaults to &laquo;)
- `remove_query_indicator="y"` -- use this if you're finding that the keyword and pagination arguments aren't registering when searching (you always get "no keywords" error). This is likely due to a combination of using PHP as CGI and using a query indicator (?) in your .htaccess Rewrite rule that removes index.php. Note that this will also mean that you'll need to use the POST method on your search form.
- `remove_title=" | Your Site Name"` -- text string to remove from the title of your search results (e.g. the name of your site, preceeded or appended by a pipe or colon).
- `safe="off"` -- level of safe search filtering ("high", "moderate", or "off" (default)). (Yes, I am a sick, perverted bastard.)
- `site="yoursite.com"` -- domain to restrict searches to. (Leave out to get generalized Google search results.)

----------------------

##Variables

`{results}{/results}` -- the main search results tag pair. Within this loop you can use the following variables:

- `{title}` -- the page title of the search result
- `{url}` -- the URL of the search result
- `{excerpt}` -- Google's excerpt from the search result, with keywords emphasized
- `{cached_url}` --  link to Google's cached copy of the search result

You can also use both `{count}` and `{total_page_results}` tags within the loop, as variables or conditionals.

Outside the `{results}` tag pair you may use the following tags:

- `{keywords}` -- the keywords you searched for
- `{total_search_results}` -- total number of results returned via the API
- `{total_google_search_results}` -- total number of results available via Google for your search
- `{page_number}` -- current page number (1 through 8) of the results
- `{total_pages}` -- total number of pages of results for your query
- `{google_results_url}` -- URL to your search on Google

---------------------

##Conditionals

- `{if paginate}` -- TRUE if there is more than one page of results returned
- `{if search_results}` -- TRUE if there are more than 0 results for your search
- `{if no_search_results}` -- TRUE if there are no results from your search
- `{if results_overflow}` -- TRUE if the total number of available results via Google exceeds the API's limt of 64
- `{if last_results_page}` -- TRUE if you're on the last page of your search results

-------------------

##Keywords

You can also display the search keywords outside of the `{exp:google_search_results}` tag pair using `{exp:google_search_results:keywords}`.

---------------------

##Example template

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
		<p><a href="{google_results_url}">View all {total_google_search_results} results on Google</a></p>
		{/if}
		
	{/exp:google_search_results}

-----------------------

##Compatibility

This plugin has been tested with EE 1.6.8.  An EE 2.0 version will come, I'm sure. If you're running PHP < 5.2, you'll need to upload the included JSON.php file to your `/system/lib/` directory. Also, cURL support is required!


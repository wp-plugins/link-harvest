=== Link Harvest ===
Contributors: crowdfavorite, alexkingorg
Donate link: http://crowdfavorite.com/donate/
Tags: link, links, report, reporting, blogroll
Requires at least: 3.0
Tested up to: 3.0.1
Stable tag: 1.3

Link Harvest will go through all of your posts and compile a list of all external links. It will then create a live updating linkroll for you.

== Description ==

Links:

- [Link Harvest in Actions](http://alexking.org/links).
- [Wordpress.org Link Harvest forum topics](http://wordpress.org/tags/link-harvest?forum_id=10).
- [Link Harvest plugin page at Crowd Favorite](http://crowdfavorite.com/wordpress/plugins/link-harvest/).
- [Plugin forums at Crowd Favorite](http://crowdfavorite.com/forums/forum/link-harvest).
- [WordPress Help Center Link Harvest support](http://wphelpcenter.com/plugins/link-harvest/).

Link Harvest will go through all of your posts and pages and compile a list of all external links. It will then create a live updating linkroll for you, based on your actual linking activity. The more you link to a particular site, the higher it will appear on the list, which can be shown in a full page display or within your sidebar. The plugin also includes tool to view the individual links to each site and the pages on which the links appear.

When you first install Link Harvest you must manually tell it to harvest all your links in the options page. After that, Link Harvest will update the link list automatically every time you create, delete or update a post. 

If you wish to display the link list on your site, you can use shortcodes or template tags. 

Template tags: 
	
- `<?php if (function_exists('aklh_show_harvest')) {aklh_show_harvest($limit = 50, $type = "table" or "list")} ?>`
- `<?php if (function_exists('aklh_top_links')) {aklh_top_links($limit = 10, $type = "list" or "table")} ?>`

Shortcode: `[linkharvest type="list or table" limit="50"]`

== Installation ==

1. Download the plugin archive and expand it (you've likely already done this).
2. Put the plugin files into your wp-content/plugins/ directory.
3. Go to the Plugins page in your WordPress Administration area and click 'Activate' for Link Harvest.
4. Navigate to the Link Harvest settings page and under the Link Harvest tab, click the Harvest Links button. This may take several hours to run depending on your site, leave the browser window open until it completes.

== Frequently Asked Questions ==

= My harvest got screwed up, how do I start over? =

You can re-harvest your links at any time by clicking the (Re)Harvest All Links button in the 'Link Harvest' tab on the options page (Options > Link Harvest).

= Why won't some of my links appear in the links list? =

Link harvest only harvest links that begin with http:// or https://. By default Link Harvest will not harvest links to various media file types (.jpg, .gif, .mp3 etc..), there is a filter hook to modify the extensions to exclude.

= Is there a way to fill empty page titles? =

Yes, simply go to the Link Harvest options page and use the links under the 'Backfill Empty Titles' heading (under the 'Harvest Links' tab).

= Do I have to Re harvest my links if I remove a domain from the exclusion list? =

No, when you harvest links, Link Harvest will harvest all links but only display those domains not entered in the exclusion list. 

= What is the difference between the two template tags? =

`aklh_top_links()` By default will give you a list of your top domains without an option to show/hide links and posts. This is to make it viable for side bar display.

`aklh_show_harvest()' By default will display a table of your domains with links to show and hide the links and associated posts. 

= Anything else? =

That about does it - enjoy!

== Screenshots ==

1. The admin table displaying all the links harvested.
2. The user interface to begin harvesting links.
3. Harvested domains displayed in list format
4. Link Harvest in action on alexking.org

== Changelog ==

= 1.3 =
- New : Ability to show/hide number of times links or domains have been used.
- New : Integrated CF_Admin class for options display
- New : List type Linkroll now displays show/hide links and posts for each domain
- New : `[linkharvest]` shortcode
- New : Option to turn off powered by link
- New : Support for custom post types
- Changed : Show Posts/Show Pages Javascript, now toggles display on link click as well
- Changed : Admin table formatting using WordPress core CSS
- Changed : Deprecated token method
- Changed : Domain exclusion list excludes on display as opposed to on harvest
- Bugfix : Added various escaping and data validation
- Bugfix : Updating a post re-added the links of that post to the domain counter and database

= 1.2 =
- New : AKLH_LOADED check.
- New : Added install function.
- Changed : Removed old code that relied on the Prototype Library.

= 1.1 =
- New : Debug code and logging functionality
- New : Exclude file extensions functionality.
- Changed : Translation text domain to match plugin-slug
- Changed : Table prefix codes.

= 1.0 =
- New : The first version.

== Harvesting Your Links ==

Once you have installed Link Harvest, you need to run a harvest to pull out links from your existing content. Running a harvest is simple, but it takes time. The harvest on a blog with ~1250 posts with links took several hours. You need to leave your browser open during this time for the harvest to complete.

To run a harvest, go to the Link Harvest options page (Options > Link Harvest),  click on the 'Harvest Links' tab then click the button to begin a harvest and follow the steps on the screen.

Note, harvest actions must be enabled (in the options tab) to do a harvest. Harvest actions are automatically disabled after a completed harvest.

Once you have completed a harvest, any links you add in future posts will be added incrementally as you create the posts.

== Viewing your Link Harvest ==

Your links list is available to you at all times in the WordPress Admin interface (Dashboard > Link Harvest). You can also choose to show your links list in your blog.

== Showing your Link Harvest ==

= Shortcode =

To show a list or table of your harvested links in a post (or page), use the following shortcode:

`[linkharvest type="list or table" limit="50"]`

= Template Tag Method =

You can always add a template tag to your theme (in a page template perhaps) to show your links list:

`<?php if (function_exists('aklh_show_harvest')) { aklh_show_harvest(50); } ?>`

== Adding a links list/blogroll to your sidebar ==

To add a links list to your sidebar (like a blogroll), you can use the following template tag:

`<?php if (function_exists('aklh_top_links')) aklh_top_links(); ?>`

This will show the top 10 domains, to show a different number of links set the number like so:

`<?php aklh_top_links(25); ?>`
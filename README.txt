=== Share This ===
Tags: link, links, report, reporting, blogroll
Contributors: alexkingorg
Requires at least: 2.0
Tested up to: 2.0.5
Stable tag: 1.0

Link Harvest will go through all of your posts and pages and compile a list of all external links. Then it will create a live updating linkroll for you, based on your actual linking activity.

== Installation ==

1. Download the plugin archive and expand it (you've likely already done this).
2. Put the 'link-harvest.php' file into your wp-content/plugins/ directory.
3. Go to the Plugins page in your WordPress Administration area and click 'Activate' for Link Harvest.
4. If you are using a version of WP prior to 2.1, upload the included prototype.js to your wp-includes/js/ directory


== Harvesting Your Links ==

Once you have installed Link Harvest, you need to run a harvest to pull out links from your existing content. Running a harvest is simple, but it takes time. The harvest on my blog (~1250 posts with links) took several hours. You need to leave your browser open during this time for the harvest to complete.

To run a harvest, go to the Link Harvest options page (Options > Link Harvest), click the button to begin a harvest and follow the steps on the screen.

Note, harvest actions must be enabled (on the options page) to do a harvest. Harvest actions are automatically disabled after a completed harvest.

Once you have completed a harvest, any links you add in future posts will be added incrementally as you create the posts.


== Viewing your Link Harvest ==

Your links list is available to you at all times in the WordPress Admin interface (Dashboard > Link Harvest). You can also choose to show your links list in your blog.


== Showing your Link Harvest ==


= Token Method =

The token method is the easier way to show your links list, and is enabled by default. To show your links list, simply add the following to a page or post:

`###linkharvest###`

and your links list will appear in this place in the page/post.


= Template Tag Method =

You can always add a template tag to your theme (in a page template perhaps) to show your links list:

`<?php aklh_show_harvest($count = 50); ?>`


== Adding a links list/blogroll to your sidebar ==

To add a links list to your sidebar (like a blogroll), you can use the following template tag:

`<?php aklh_top_links($count = 10); ?>`


== Known Issues ==

= Token Processing Time =

Using the token method to show your links list will add *very* minor additional processing to each post display on your site.


== Frequently Asked Questions ==

= My harvest got screwed up, how do I start over? =

You can re-harvest your links at any time by clicking the (Re)Harvest All Links button on the Link Harvest options page (Options > Link Harvest).


= Is there a way to fill empty page titles? =

Yes, simply go to the Link Harvest options page and use the links under the 'Backfill Empty Titles' heading.


= Anything else? =

That about does it - enjoy!

--Alex King

http://alexking.org/projects/wordpress
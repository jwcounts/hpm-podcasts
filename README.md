# Podcasts by Category

A Wordpress plugin that allows you to create a podcast feed from any category, either video or audio. It also has the option to periodically cache the feeds as flat XML files (on a separate server via FTP or SFTP, on Amazon S3, or locally) to speed up delivery.  It can also be set up to upload the media files to those other servers.

## Why does this exist?

I couldn't find a podcasting plugin that functioned how I wanted, so I made one myself.

I also wanted to learn some of the ins and outs of creating a Wordpress plugin, and this seemed like a decent way to learn.

## Getting started

Download this repo, unzip it, and drop the hpm-podcasts folder into your wp-content/plugins folder.  Then log into your site, go to the Plugins area and activate it.

Once it is activated, look for Podcasts in the left-hand menu of the Admin Dashboard.  If you are logged in as an admin, you will see `Settings` listed in that section.  From there, you can set up the owner of the podcast, how frequently the feeds are updated, and all of your upload options.

## Your Podcast Feeds

You create them in much the same way as you would create a page, but with more metadata to populate.  I will flesh this out more later.

## Creating Episodes

Create a post and attach it to the category you selected when setting up your podcast feed.  That's pretty much it.  There are some options to create an podcast feed-specific description for your episode, but that is purely optional.

## Customizing Your Podcasts or Archive Listing

If you want to customize the podcast archive listing, you can do so by creating a folder in your theme called `podcasts` and saving your `archive.php` template file in there.  You can also do that with `single.php`, but there isn't much you can change on that at the moment.

## Wishlist

This project is a work in progress.  If you have ideas or run into problems, open an issue!

## Questions

Contact me at jcounts@houstonpublicmedia.org.

## License

Copyright (c) 2017 Houston Public Media

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

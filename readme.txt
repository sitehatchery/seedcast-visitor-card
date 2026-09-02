=== Visitor Card ===
Contributors: seedcast
Tags: church, visitors, form, contact, welcome
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.20.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A visitor card for your church website. Someone can say they are coming, and you will know to look for them.

== Description ==

Somebody finds your church online on a Thursday. They listen to a sermon, read a bit, decide they might come on Sunday. Then nothing happens, because there was nothing for them to do except turn up cold.

This adds the one thing that was missing: a card that says "Coming to visit us? Let us know." They fill it in, you get an email, and they get your address and service times to look at on the day.

= What happens when someone fills it in =

Three things, straight away.

**You get an email.** Their name, how to reach them, how many are coming, and anything they wanted you to know. Replying to it replies to them, not to your website.

**The card becomes your details.** Your service day, times and address, right where the form was, so they have it in front of them straight away.

**They get an email.** Your church name, address, service times and a directions link. This is the part that matters most and the part most churches skip. Someone who decides on a Thursday will not remember your address by the weekend, and this is what they will look at in the car.

**The card changes.** Everywhere it appears, it becomes "We cannot wait to see you on Sunday, Sarah." It stays that way until the service has passed, then it goes back to being a card for the following week.

= It goes where people already are =

The card appears at the end of your sermons, services and songs automatically, if you run the other Seedcast plugins. That is deliberate. Somebody who has just listened to a message is closer to visiting than somebody who navigated to your contact page, and asking them right there is the whole idea.

It never appears on lists, and never on your ordinary posts and pages. Anywhere else you want it, place it by hand with the shortcode or the Elementor widget.

= Built for a church, not adapted from a contact form =

* Asks how many are coming, so you know whether it is one person or a family of five
* Asks how they heard about you, which is worth knowing
* Can ask where they are with you and what they want to know more about, the way a printed connection card does, with the questions and choices written to match your own card
* Templates modeled on the cards churches already hand out, so it looks familiar rather than like a contact form
* Knows which day you gather, so a church meeting on Saturday says Saturday
* Can be paused for a week you are not gathering, so nobody is invited to a service that is not happening
* Needs a name, and then either an email address or a phone number. Insisting on email turns away the people who would rather be phoned

= Your contacts stay yours =

Everything is stored on your own site. Nothing is sent anywhere, there is no account to create, and there is no service behind this plugin. The list can be exported as a CSV whenever you want it, and deleted whenever you want that.

It is not a mailing list and it will never become one. One email goes to a visitor because they asked for it, and nothing follows it.

= Shortcodes =

* `[scvc_visitor_card]` places the card
* `[scvc_visitor_card template="contact"]` uses the plain contact form style
* `[scvc_visitor_card details="always"]` puts the service details on the form as well
* `[scvc_visitor_card heading="Planning a visit?"]` changes the wording in one place
* `[scvc_visitor_card color="#8b5a2b"]` uses a different color for one card
* `[scvc_visitor_card template="bold" header="#14161a"]` sets the band color on the bold template

= Works better with the rest =

Your church name, address, service day and service times are shared with every Seedcast plugin, so they are filled in once and this uses them. Sermon Library and Living Bulletin are free.

== Installation ==

1. Install and activate.
2. Go to Visitor Card, Settings.
3. Fill in your church details if you have not already. The email a visitor receives is mostly your address and service times, so it will be thin until they are set.
4. The card appears on your Seedcast content automatically. Place it anywhere else with `[scvc_visitor_card]`.

== Frequently Asked Questions ==

= Where does the card appear? =

At the end of your sermons, services and songs, if you run those plugins. Never on archive or listing pages, and never on your ordinary posts and pages. You can switch any of that off, and place the card anywhere by hand.

= What if we are not meeting this week? =

Turn on the pause in settings and write a line explaining. The card is replaced with your message everywhere it appears and stops accepting submissions, so nobody is invited to a service that is not happening.

= Do visitors get added to a mailing list? =

No. They get one email, because they asked for it, with your address and service times. Nothing else is ever sent and there is no list to join.

= Is there spam protection? =

Yes. A honeypot is always on, and you can add reCAPTCHA, Cloudflare Turnstile or hCaptcha under Settings, Seedcast, Spam protection. That is set once and every Seedcast form uses it, including this one.

= Can I see which pages bring people in? =

Yes. Under Visitor Card, Where from, you can see how many cards came from each page. It is counted from the cards themselves, so nobody is tracked and nothing is stored about people who did not fill one in. It counts cards, not visits, so it cannot tell you how many people saw the card and passed.

= Does anything leave my site? =

No. Cards are stored in your own database and are never sent anywhere. The plugin has no account, no service behind it, and makes no outbound calls of its own.

= How do I test it myself? =

Just fill it in. While you are signed in as an administrator the card will not remember you, so you can send it repeatedly: you see the confirmation each time and the form again on the next page. Your test submissions are stored like any other, so you can check the list and the emails, and delete them afterwards.

= What does it store in the visitor's browser? =

One cookie, set only after they send the card, so the card does not keep asking somebody who has already answered. It holds the name they gave and the date the service falls on, and it expires once that service has passed. Someone who never fills the card in is never given a cookie.

= Someone asked us to delete their details. =

Use Tools, Erase Personal Data, the same as anything else. This plugin is wired into it. You can also delete anyone directly from the Visitors screen.

= Can I change what it says? =

All of it. The heading, the opening line, the button, the consent line, the confirmation, and both emails.

= Our church meets on Saturday. =

Set your service day in the shared church settings and everything follows, including the confirmation message and when it expires.

== Screenshots ==

1. The card at the end of a sermon
2. What a visitor sees after they send it
3. Every submission, grouped by person
4. Which pages bring people in
5. Settings

== Changelog ==

= 1.20.0 =
* Every setting the shared Seedcast library stores was renamed to a longer, more distinctive prefix, so it cannot collide with another plugin that happened to choose the same short one. Your church details, service times, theme and spam settings are carried across automatically the first time the plugin loads; nothing needs re-entering.
* Submitted cards are cleaned once, in a single place, before the card is read. A name or message containing a backslash is no longer damaged on its way into the database.
* Internal tidying so the plugin passes WordPress's own Plugin Check cleanly: the CSV export writes through standard output, and two database queries carry a note explaining why their prepared values cannot be counted automatically.
* The card's form fields and icons are escaped against an allow list when they appear in another plugin's page, such as a service page sidebar. The form itself is unchanged.

= 1.17.0 =
* Your city, state and postcode are now their own fields under Settings, Seedcast, Church. Existing addresses are untouched and still read as they did.
* Fixed the email a visitor receives losing the town once the address was split. It now sends the whole address either way.
* New tokens for the visitor email: {city}, {state} and {locality}.

= 1.16.0 =
* New screen: where cards come from. Which pages people were reading when they told you they were coming, so you can see whether your sermons are bringing anyone.
* Counted from the cards themselves. Nobody is tracked, nothing is recorded about people who do not fill one in, and no page views are counted.
* Submissions now record which post they came from, so a permalink change no longer splits one page into several rows.

= 1.15.0 =
* When and where now appears after somebody sends the card rather than above the form. On the form it pushed the fields down the page and usually repeated what was already there; once somebody has committed it is the thing they most want. You can still put it on the card as well, or turn it off.

= 1.14.1 =
* The fields now sit in a panel of their own inside the card, with room around them.

= 1.14.0 =
* Fixed the opening line sitting further right than the heading. The heading was outside the padded part of the card and everything under it was inside.
* Themes can no longer put a second box inside the card. Many style a bare form with a background and padding, which was taking width the fields needed.
* Field edges now take their color from your accent rather than a flat gray, so they belong to the card.

= 1.13.1 =
* Fixed the Submissions screen showing only the most recent card from somebody who filled it in more than once. Everything they wrote earlier was still stored but never displayed. Earlier cards now sit under the newest one, each with its own date and content.

= 1.13.0 =
* Tightened the card so it fits a narrow column: less padding on the fields, the card and the sidebar.
* Field borders now come from your Seedcast theme rather than being fixed.
* A name is always asked for and always required. It could be switched off before, which meant the form could mark a hidden field as required and refuse to submit without it.
* The form now says an email address or a phone number is enough. That rule existed but only appeared as an error after pressing the button.
* When only one way to reach you is asked for, it is marked as required.
* Church details now offer a link to check your address on the map. It is the same link visitors are given, so pressing it is the check.

= 1.12.0 =
* You can now test the card on your own site as often as you like. Signed in as an administrator, the card does not remember you: you see the confirmation each time you send it, and the form again on the next page. Visitors are remembered as before.

= 1.11.0 =
* The shortcode is now `[scvc_visitor_card]`, matching the rest of the suite. The old `[visitor_card]` still works, so anything you have already placed keeps working.
* Fixed the admin showing your own confirmation. Filling the card in on the front end set a cookie that the Card Designer was reading, so the preview showed the confirmation instead of the form.

= 1.10.0 =
* The card now says when and where you meet: your service day, times and address, taken from your church details. Somebody deciding whether to come needs to know that before they commit, not after.
* The same details show once they have sent the card, which is when they need the address most.
* Can be switched off, or left off for one placement on a page that already lists your times.

= 1.9.0 =
* Submissions now has New, Done and Archived tabs, with a count on each.
* Archive sits next to delete, and tidying by date archives rather than deletes. Deleting for good is still there, per person, where it is deliberate.
* You can sort by who and by when.
* Settings moved to the bottom of the menu.
* Post types with no page of their own are no longer offered under where the card appears, since it could never show there.
* The Elementor widget has a Style tab: colors, typography, padding, corners and the button.
* The confirmation on the bold card is a light panel rather than a darker patch of the accent, which was hard to read.

= 1.8.1 =
* The Submissions screen now shows where somebody is with you and what they are interested in. Those were reaching your email and the export but not the screen you actually work from.

= 1.8.0 =
* When somebody sends the card it now turns into your confirmation, in place. It used to put a notice at the top of a form the person had already scrolled past, and the wording said their submission would be reviewed, which is not what happens.
* The confirmation is styled as good news rather than as a system message, and drops the opening line, which was still telling them to let you know.
* Requires the shared library at 1.18.0, included.

= 1.7.0 =
* The card now matches the rest of your site by default, taking its colors from the theme you chose under Settings, Seedcast, Appearance. Picking your own colors is an override, and you can go back to matching at any time.
* Stopped forcing the heading, labels and button to capitals. If you want capitals, type them.

= 1.6.2 =
* Fixed the text color choice on mid tones. A gold or tan accent was getting white text at around 2 to 1 contrast when black would have given better than 9 to 1. It now compares the two properly instead of using a cutoff.
* Fixed the button running outside its own edge in a narrow column, and long labels now wrap.
* Fields have a visible edge, so they can never disappear into a background of the same color.
* The card is more compact in a sidebar, where the full padding left little room for the fields.

= 1.6.1 =
* Text color is now always worked out from the background, and is no longer something you can set. An arbitrary text color is a way to end up with red on tan, which is what working it out is there to prevent.
* The shortcode generator has the header color too, and its preview now shows the text color it will actually use.
* A shortcode with a mistyped color now falls back to your design instead of the built-in default.

= 1.6.0 =
* Fixed the card on Sermon Library pages ignoring the template you chose. It was forcing its own, so a design you saved never reached your sermons.
* Text color is now worked out from the colors you pick, so a pale accent gets dark text instead of white on cream. You can still set an exact color if you need one.

= 1.5.0 =
* Each choice can now be switched off on its own. If your church does not do memberships, switch Membership off and the rest stay. The wording is kept, so it is there if you ever need it.
* Anyone who chose something before it was switched off still reads properly in your list and your emails, rather than showing a tidied-up version of the key.
* The toggles in the Card Designer are switches now.

= 1.4.1 =
* Fixed the submissions table not being created when Visitor Card is the first Seedcast plugin on a site, which left the Submissions screen with nothing to read.
* That screen now says so plainly if the table is missing, instead of looking like nobody had filled the card in.
* Renamed Visitors to Submissions, which is what people look for.

= 1.4.0 =
* The wording moved to the Card Designer, next to the preview, instead of sitting on a settings page where you could not see what you were changing. The heading, opening line, button, consent line and confirmation are all there now.
* You can preview the message somebody sees after sending the card, which until now was the one thing you could not check before it went out.
* Settings is now only what happens around the card: where it appears, the emails, and pausing it.

= 1.3.0 =
* The two choice groups can now be written to match your own card. Change the question and the choices, one per line, in the Card Designer.
* Renaming a choice does not change cards people have already filled in. Those keep the words they were given.

= 1.2.1 =
* Fixed the card designer refusing to save. The preview was a working form inside the page's own form, so saving tried to validate a card you were only looking at. The preview is now a picture of the card rather than a form.
* The bold connection card is inverted: the accent color now fills the card and the fields sit on it as white boxes, with the header band taking a second color you can set.

= 1.2.0 =
* New Card Designer screen. What the card asks and how it looks are set there, next to a live preview, rather than on a settings page where you cannot see what you are changing.
* The shortcode generator now overrides a single placement only, and takes its starting point from the designer.
* Moved the extra CSS class field next to the template and color, where it belongs.
* Simplified where the card appears: choose the content types and that is the setting, instead of a switch on top of a list.
* The email a visitor receives now arrives already written, so you can edit it instead of facing an empty box.

= 1.1.0 =
* Two new templates modeled on the connection cards churches already hand out: a classic one with ruled lines, and a bold one with a heavier header band.
* Template and color are now chosen together on the card designer, with the preview updating as you pick.
* New optional fields: where somebody is with you (first visit, second visit, regular attendee, member) and what they would like to know more about (membership, baptism, small groups, serving, volunteering). Both off to start with.
* On Sermon Library the card now appears in the sidebar under the scripture references, rather than at the foot of the page. Needs Sermon Library 2.51.0.
* A pause can now be given an end date, so the card comes back by itself.
* You can mark somebody as followed up, so a card you have answered no longer looks like one you have not.
* The email a visitor receives now says plainly that it is the only one and that there is no list.

= 1.0.2 =
* Moved the style and color onto the card designer, where you can see the card change as you pick them. Choosing a color without seeing it on the card was guesswork.
* Added a color attribute to the shortcode, so one placement can differ from the rest.
* Fixed a fault where saving the settings tab would have cleared the style and color.

= 1.0.1 =
* Settings now appear as a tab on the shared Seedcast settings page, alongside your other Seedcast plugins, instead of on a page of their own.
* Added a shortcode generator under Visitor Card, Shortcodes, with a live preview of the card.
* Fixed the link to church details, which pointed at a page that does not exist.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.20.0 =

Settings stored by the shared Seedcast library move to a new, longer prefix. They are carried across for you on the first page load after upgrading, so nothing needs re-entering. Includes input handling improvements.

= 1.17.0 =

Settings have moved to the shared Seedcast settings page, as a tab. Adds a shortcode generator with a live preview.

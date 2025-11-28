Link Previews
Updated: Nov 5, 2025
WhatsApp supports link previews when the link is sent via chat or shared via status. WhatsApp will attempt to perform a link preview when possible for a better user experience. To enable this experience, WhatsApp relies on link owners to define properties that are specifically optimized for WhatsApp. Not meeting these requirements may risk the link to be not previewed.


Get Started
To get started with enabling link previews, websites need to add HTML mark-ups to the HEAD section on the page.
<head>
  <meta property="og:title" content=”WhatsApp"/>
  <meta property="og:description" content="Simple. Secure. Reliable messaging."/>
  <meta property="og:url" content="https://whatsapp.com"/>
  <meta property="og:image"content="https://static.whatsapp.net/rsrc.php/ym/r/36B424nhiL4.svg"/>
</head>

The <head> containing the HTML mark-ups must appear within the first 300KB of the HTML. The entire HTML does not need to fit within 300KB.
The <og:title>, <og:description> and <og:url> mark-ups must be inside the <head> tag. They should not be empty.
The <og:title> mark-up represents the title of the content without any branding. WhatsApp will display this in primary text color, in bold and in at most 2 lines.
The <og:description> mark-up represents the description of the content. WhatsApp will display this in a smaller size than the title and in secondary text color. It is limited to 1 or 2 lines and 80 characters will suffice.
The <og:url> mark-up represents the canonical URL of the page. The URL should be undecorated, without session variables, user identifying parameters and counters.
The <og:image> mark-up is an absolute URL for an image used as the thumbnail for the link preview. This image should be under 600KB in size. Image should be 300px or more in width with 4:1 width/height or less aspect ratio.
WhatsApp will make the best attempt to show link previews, eg: relaxing requirements, looking for other HTML mark-ups and reverting to small link previews. However, this should not be relied on. It’s not guaranteed to work (and continue to work).
WhatsApp crawls the web page via an HTTP GET request.
The request will have the User-Agent header set to WhatsApp/2.x.x.x A|I|N, where x are major/minor numeric versions of WhatsApp and A|I|N is for Android, iOS and web respectively. Some examples of valid User-Agent header values: WhatsApp/2.22.20.72 A, WhatsApp/2.22.19.78 I, WhatsApp/2.2236.3 N. Web site owners can identify such incoming requests and can customize the content (mark-ups and images) accordingly.
The request will also have the Accept-Language header set to the language selected by the recipient, if any. Some examples of valid Accept-Language header values are: en , fr, de. Similarly, web site owners can customize the content language accordingly. Note that the language set by the recipient will also be seen by the recipient.
How to verify?
Start with composing a message with the link to test (not tap to send yet). On behalf of the sender, WhatsApp will crawl this URL and attempt to generate a link preview.
If a preview does not come up above the composer box after 10 seconds, please check all the requirements above are met. Else, continue with sending the message by tapping the “send” button.
If a preview does not show up in the expected large size, please check the image requirements above are met. Else, link previews are all working as expected. Congratulations, you’re all set!
---
description: Implementing a client-side tracking script for CDP.
---

# Implement client-side tracking for CDP

To implement a tracking script, you will need to insert a head script between
the `<head></head>` tags on your website:

```js
<script type="text/javascript">

window.raptor||(window.raptor={q:[{event:"trackevent",params:{p1:"pageview"}}],push:function(event,params,options){this.q.push({event:event,params:params,options:options})},customerId:"XXXX"});

</script>
```

For the script to work, you need to fill in `customerId:”XXXX` with your specific account number in the Ibexa CDP Control Panel.

Next, place the main script under the head script, and cookie consent:

```js
<script type="text/javascript">

(function(d,t){var g=d.createElement(t),s=d.getElementsByTagName(t)[0];g.src="//az19942.vo.msecnd.net/script/raptor-3.0.min.js",g.async="true",s.parentNode.insertBefore(g,s)}(document,"script"));

</script>
```

Now, you can move on to setting up specific events that should be tracked.

## Add tracking events

Now, you need to add a tracker to specific places in your website where you want to track users.
For example, add this tracker to the Landing Page template if you want to track user entrances.

```js
raptor.trackEvent('visit', ..., ...);
```
or buys:

```js
  //Parameters for Product 1
raptor.trackEvent('buy', ..., ...);
  //Parameters for Product 2
raptor.trackEvent('buy', ..., ...);
```

## Add user ID

*A user is typically recognized by their id when they log-in, signs up for a newsletter or buys a product.
When you have recognized a user on your website by their id (email), send it to Raptor by this method:*

For tracing to be effective, you also need to send ID of a logged-in user in the same way.

Add the user ID information by using below script:

```js
raptor.push("setRuid","USER_ID_HERE")
```

For more information on tracking events, see [the documentation](https://support.raptorsmartadvisor.com/hc/en-us/articles/201912411-Tracking-Events).


NOTE: It is necessary to set the rsa cookie server-side to avoid the cookie expiry being set to 7 days in Safari on Mac and iOS (due to Intelligent Tracking Prevention by Apple). See how to make a workaround here  
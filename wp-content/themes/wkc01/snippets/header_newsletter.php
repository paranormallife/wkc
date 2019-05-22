<div id="newsletterIcon" class="header-newsletter-icon">
    <span onclick="newsletterToggle()"><i class="far fa-newspaper"></i></span>
</div>

<div id="newsletterSignup" class="header-newsletter-signup">
    <div class="container">
        <div class="description">
            Subscribe to our newsletter:
        </div>
        <div class="form">
            <!-- Begin Mailchimp Signup Form -->
            <div id="mc_embed_signup">
            <form action="https://westkc.us18.list-manage.com/subscribe/post?u=7631619f8e0a6c10ac7f7fdb8&amp;id=d37a9bc4b3" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate unstyled" target="_blank" novalidate>
                <div id="mc_embed_signup_scroll">
                
            <div class="mc-field-group signup-form">
                <input type="email" value="" name="EMAIL" class="required email" id="mce-EMAIL" placeholder="you@example.com">
                <input type="submit" value="Subscribe" name="subscribe" id="mc-embedded-subscribe">
            </div>
                <div id="mce-responses" class="clear">
                    <div class="response" id="mce-error-response" style="display:none"></div>
                    <div class="response" id="mce-success-response" style="display:none"></div>
                </div>    <!-- real people should not fill this in and expect good things - do not remove this or risk form bot signups-->
                <div style="position: absolute; left: -5000px;" aria-hidden="true"><input type="text" name="b_7631619f8e0a6c10ac7f7fdb8_d37a9bc4b3" tabindex="-1" value=""></div>
                <div class="clear"></div>
                </div>
            </form>
            </div>
            <script type='text/javascript' src='//s3.amazonaws.com/downloads.mailchimp.com/js/mc-validate.js'></script><script type='text/javascript'>(function($) {window.fnames = new Array(); window.ftypes = new Array();fnames[0]='EMAIL';ftypes[0]='email';fnames[1]='FNAME';ftypes[1]='text';fnames[2]='LNAME';ftypes[2]='text';fnames[3]='ADDRESS';ftypes[3]='address';fnames[4]='PHONE';ftypes[4]='phone';fnames[5]='BIRTHDAY';ftypes[5]='birthday';}(jQuery));var $mcj = jQuery.noConflict(true);</script>
            <!--End mc_embed_signup-->
        </div>
    </div>
</div>

<script>
	function newsletterToggle() {
		var element = document.getElementById("newsletterIcon");
		element.classList.toggle("active");
		var element = document.getElementById("newsletterSignup");
		element.classList.toggle("active");
		var element = document.getElementById("navToggle");
		element.classList.remove("active");
		var element = document.getElementById("navIcon");
		element.classList.remove("active");
	}
</script>
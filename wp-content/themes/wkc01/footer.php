<!-- FOOTER.PHP ++++++++++++++++++++++ -->


<div class="footer-wrapper">

    <footer>
        <div class="footer-contact">
            <h2>West Kortright Centre</h2>
            <p>49 West Kortright Church Road</p>
            <p>East Meredith, NY 13757 <a class="map" href="/contact"><i class="fas fa-map-marker-alt map-icon"><span>Directions</span></i></a></p>
            <p><a href="tel:+16072785454">607-278-5454</a></p>
        </div>
        <div class="footer-actions">
            <h2>Follow Us</h2>
            <div class="footer-social">
                <ul>
                    <li class="email"><a href="mailto:info@westkc.org"><i class="fas fa-envelope-square"><span>info@westkc.org</span></i></a></li>
                    <li class="facebook"><a target="_blank" href="https://www.facebook.com/westkortrightcentre"><i class="fab fa-facebook-square"><span>Facebook</span></i></a></li>
                    <li class="instagram"><a target="_blank" href="https://instagram.com/westkortrightcentre"><i class="fab fa-instagram"><span>Instagram</span></i></a></li>
                    <li class="twitter"><a target="_blank" href="https://twitter.com/49wkc"><i class="fab fa-twitter-square"><span>Twitter</span></i></a></li>
                </ul>
            </div>
            <ul>
                <li class="donate"><a href="#">Donate</a></li>
                <li class="search" id="footer_search_toggle_button"><span onclick="footerSearchToggle()">Search</span></li>
                <li class="newsletter" id="footer_newsletter_toggle_button"><span onclick="footerNewsletterToggle()">Subscribe</span></li>
            </ul>
            <div class="footer-search" id="footer_search_toggle">
                <form action="/" method="get">
                    <input type="text" name="s" id="search" placeholder="Search this site" value="<?php the_search_query(); ?>" />
                    <input type="submit" value="Search" />
                </form>
            </div>
            <div class="footer-newsletter" id="footer_newsletter_toggle">
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
        <div class="footer-nysca">
            <h2>New York State Council on the Arts</h2>
            <?php get_template_part('snippets/nysca_logo'); ?>
            <p>WKC programming is funded in part by New York State Council on the Arts with the support of Governor Andrew Cuomo and the New York State Legislature.</p>
        </div>
        <div class="footer-copyright">
            &copy; <?php echo date('Y '); bloginfo('name'); ?>
        </div>
    </footer>

</div>

<script>
	function footerSearchToggle() {
		var element = document.getElementById("footer_search_toggle");
		element.classList.toggle("active");
		var element = document.getElementById("footer_search_toggle_button");
		element.classList.toggle("active");
		var element = document.getElementById("footer_newsletter_toggle");
		element.classList.remove("active");
		var element = document.getElementById("footer_newsletter_toggle_button");
		element.classList.remove("active");
	}
	function footerNewsletterToggle() {
		var element = document.getElementById("footer_newsletter_toggle");
		element.classList.toggle("active");
		var element = document.getElementById("footer_newsletter_toggle_button");
		element.classList.toggle("active");
		var element = document.getElementById("footer_search_toggle");
		element.classList.remove("active");
		var element = document.getElementById("footer_search_toggle_button");
		element.classList.remove("active");
	}
</script>

<?php /* Include this so the admin bar is visible. */ wp_footer(); ?>

</body>
</html>
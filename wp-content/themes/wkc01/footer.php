<!-- FOOTER.PHP ++++++++++++++++++++++ -->


<footer>
    <div class="footer-contact">
        <h2>West Kortright Centre</h2>
        <p>49 West Kortright Church Road</p>
        <p>East Meredith, NY 13757 <a class="map" href="/contact"><i class="fas fa-map-marker-alt map-icon"><span>Directions</span></i></a></p>
        <p><a href="tel:+16072785454">607-278-5454</a></p>
    </div>
    <div class="footer-social">
        <ul>
            <li class="email"><a href="mailto:info@westkc.org"><i class="fas fa-envelope-square"><span>info@westkc.org</span></i></a></li>
            <li class="facebook"><a target="_blank" href="https://www.facebook.com/westkortrightcentre"><i class="fab fa-facebook-square"><span>Facebook</span></i></a></li>
            <li class="instagram"><a target="_blank" href="https://instagram.com/westkortrightcentre"><i class="fab fa-instagram"><span>Instagram</span></i></a></li>
            <li class="twitter"><a target="_blank" href="https://twitter.com/49wkc"><i class="fab fa-twitter-square"><span>Twitter</span></i></a></li>
        </ul>
    </div>
    <div class="footer-actions">
        <ul>
            <li class="donate"><a href="#">Donate</a></li>
            <li class="search" id="footer_search_toggle_button"><span onclick="footerSearchToggle()">Search</span></li>
            <li class="newsletter" id="footer_newsletter_toggle_button"><span onclick="footerNewsletterToggle()">Subscribe</span></li>
        </ul>
    </div>
    <div class="footer-search" id="footer_search_toggle">
        <form action="/" method="get">
            <input type="text" name="s" id="search" placeholder="Search this site" value="<?php the_search_query(); ?>" />
            <input type="submit" value="Search" />
        </form>
    </div>
    <div class="footer-newsletter" id="footer_newsletter_toggle">
        <form>
            <input type="email" placeholder="yourname@example.com" />
            <input type="submit" value="Subscribe" />
        </form>
    </div>
</footer>

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
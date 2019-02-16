<!-- /snippets/header_search.php | Search field in header -->

<div id="header-search-toggle">
    <i class="fas fa-search icon" onclick="searchToggle()"></i>
</div>

<div id="header-search-field">
    <form action="/" method="get">
        <input type="text" name="s" id="search" placeholder="Search + Enter" value="<?php the_search_query(); ?>" />
    </form>
</div>

<script>
	function searchToggle() {
		var element = document.getElementById("header-search-toggle");
		element.classList.toggle("active");
		var element = document.getElementById("header-search-field");
		element.classList.toggle("active");
		var element = document.getElementById("navToggle");
		element.classList.remove("active");
		var element = document.getElementById("navIcon");
		element.classList.remove("active");
	}
</script>
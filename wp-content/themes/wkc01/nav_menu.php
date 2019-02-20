<?php $navmenu = array(
	'theme_location'  => 'nav1',
	'menu'            => '', 
	'container'       => 'false', 
	'container_class' => '', 
	'container_id'    => '',
	'menu_class'      => '', 
	'menu_id'         => '',
	'echo'            => true,
	'fallback_cb'     => 'false',
	'before'          => '',
	'after'           => '',
	'link_before'     => '',
	'link_after'      => '',
	'items_wrap'      => /* Don't wrap in a UL */ '%3$s',
	'depth'           => 2,
	'walker'          => ''
); ?>


<div class="menu-icon" id="navIcon" onclick="navToggle()">
	<i class="fas fa-bars open icon"></i>
	<i class="fas fa-times close icon"></i>
</div>

<ul id="navToggle" class="mobile-menu nav-menu">
	<?php wp_nav_menu( $navmenu ); ?>        
</ul>


<script>
	function navToggle() {
		var element = document.getElementById("navToggle");
		element.classList.toggle("active");
		var element = document.getElementById("navIcon");
		element.classList.toggle("active");
		var element = document.getElementById("header-search-toggle");
		element.classList.remove("active");
		var element = document.getElementById("header-search-field");
		element.classList.remove("active");
	}

// Child Menus Toggle
  jQuery( '.menu-item-has-children' ).on( 'click', function() {
		jQuery( this ).toggleClass( 'active' );
  });

</script>
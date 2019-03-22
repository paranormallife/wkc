<div class="team-member">
    <?php
        $email = block_value('bio-email');
        $name = block_value('bio-name');
        $image = block_value('bio-image');
        $role = block_value('bio-role');
        $blurb = block_value('bio-blurb');
    ?>
    <?php if( $image != '' ) { ?>
        <div class="bio-image">
            <img src="<?php echo $image; ?>" alt="<?php echo $name; ?>" />
        </div>
    <?php } ?>
    <?php if( $email != '' ) { ?>
        <h3><a href="mailto: <?php block_field('bio-email');?>" title="Send an email to <?php echo $name; ?>"><i class="fas fa-envelope"></i></a> <?php echo $name; ?></h3>
    <?php } else { ?>
        <h3><?php echo $name; ?></h3>
    <?php } ?>
    <?php if( $role != '' ) { ?>
        <h4><?php block_field('bio-role'); ?></h4>
    <?php } ?>
    <?php if( $blurb != '' ) { ?>
        <div class="bio-blurb"><?php block_field('bio-blurb'); ?></div>
    <?php } ?>
</div>
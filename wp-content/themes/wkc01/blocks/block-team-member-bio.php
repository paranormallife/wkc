<div class="team-member">
    <?php
        $email = block_value('bio-email');
        $name = block_value('bio-name');
    ?>
    <div class="bio-image">
        <img src="<?php block_field('bio-image') ?>" alt="<?php echo $name; ?>" />
    </div>
    <?php if( $email != '' ) { ?>
        <h3><a href="mailto: <?php block_field('bio-email');?>" title="Send an email to <?php echo $name; ?>"><i class="fas fa-envelope"></i></a> <?php echo $name; ?></h3>
    <?php } else { ?>
        <h3><?php echo $name; ?></h3>
    <?php } ?>
    <h4><?php block_field('bio-role'); ?></h4>
    <div class="bio-blurb"><?php block_field('bio-blurb'); ?></div>
</div>
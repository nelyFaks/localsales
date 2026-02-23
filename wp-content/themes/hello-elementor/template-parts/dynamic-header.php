<?php
/**
 * Custom Header with Simple Navbar
 * Modified for project presentation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>

	<style>
		.custom-navbar {
			display: flex;
			justify-content: center;
			align-items: center;
			padding: 20px 0;
			background: #ffffff;
			border-bottom: 1px solid #eaeaea;
		}

		.custom-menu {
			list-style: none;
			display: flex;
			gap: 30px;
			margin: 0;
			padding: 0;
		}

		.custom-menu li a {
			text-decoration: none;
			font-family: 'Montserrat', sans-serif;
			font-weight: 600;
			color: #0B3D91;
			transition: 0.3s ease;
		}

		.custom-menu li a:hover {
			color: #555;
		}

		.site-title {
			text-align: center;
			font-size: 24px;
			font-weight: 700;
			margin-top: 15px;
			color: #111;
		}
	</style>
</head>

<body <?php body_class(); ?>>

<header id="site-header">

	<div class="site-title">
		<?php bloginfo( 'name' ); ?>
	</div>

	<nav class="custom-navbar">
		<ul class="custom-menu">
			<li><a href="<?php echo get_permalink(16); ?>">Home</a></li>
			<li><a href="<?php echo get_permalink(18); ?>">About the Project / Solution</a></li>
			<li><a href="<?php echo get_permalink(20); ?>">Target Audience</a></li>
			<li><a href="<?php echo get_permalink(22); ?>">Team</a></li>
			<li><a href="<?php echo get_permalink(24); ?>">Development Plan / MVP Roadmap</a></li>
			<li><a href="<?php echo get_permalink(26); ?>">Contact / Feedback</a></li>
		</ul>
	</nav>

</header>

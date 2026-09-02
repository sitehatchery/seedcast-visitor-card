<?php
/**
 * GENERATED FILE. DO NOT EDIT.
 * Source of truth lives in the seedcast-core repository.
 *
 * Loaded on demand by ElementorChurch::register(), never on a site without
 * Elementor, because it extends a class Elementor owns.
 *
 * @package Seedcast\Core\Frontend
 */

namespace Seedcast\Core\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Church details as an Elementor widget.
 */
class ElementorChurchWidget extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'seedcast-church-details';
	}

	/**
	 * Label shown in the Elementor panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Church Details', 'seedcast-visitor-card' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-map-pin';
	}

	/**
	 * Panel category.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( 'general' );
	}

	/**
	 * Search keywords in the Elementor panel.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'church', 'address', 'service times', 'seedcast', 'directions' );
	}

	/**
	 * Panel controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'seedcast_church_content',
			array(
				'label' => __( 'Church Details', 'seedcast-visitor-card' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'seedcast_church_intro',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'These details are edited once under Settings, Seedcast, Church. Every place they appear updates together.', 'seedcast-visitor-card' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->add_control(
			'heading',
			array(
				'label'       => __( 'Heading', 'seedcast-visitor-card' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'Optional', 'seedcast-visitor-card' ),
			)
		);

		$this->add_control(
			'fields',
			array(
				'label'    => __( 'Show', 'seedcast-visitor-card' ),
				'type'     => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'default'  => array( 'name', 'address', 'times', 'phone', 'email', 'directions' ),
				'options'  => array(
					'name'       => __( 'Church name', 'seedcast-visitor-card' ),
					'address'    => __( 'Address', 'seedcast-visitor-card' ),
					'times'      => __( 'Service times', 'seedcast-visitor-card' ),
					'phone'      => __( 'Phone', 'seedcast-visitor-card' ),
					'email'      => __( 'Email', 'seedcast-visitor-card' ),
					'events'     => __( 'Events link', 'seedcast-visitor-card' ),
					'note'       => __( 'Note to visitors', 'seedcast-visitor-card' ),
					'directions' => __( 'Directions button', 'seedcast-visitor-card' ),
				),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'seedcast-visitor-card' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'stacked',
				'options' => array(
					'stacked' => __( 'Stacked', 'seedcast-visitor-card' ),
					'inline'  => __( 'Inline', 'seedcast-visitor-card' ),
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Front-end output.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$fields = isset( $settings['fields'] ) && is_array( $settings['fields'] )
			? array_values( array_intersect( $settings['fields'], ChurchDetails::FIELDS ) )
			: array();

		$args = array(
			'heading' => isset( $settings['heading'] ) ? sanitize_text_field( $settings['heading'] ) : '',
			'layout'  => isset( $settings['layout'] ) ? sanitize_key( $settings['layout'] ) : 'stacked',
		);

		/*
		 * Only pass fields when the editor actually chose some. An empty array
		 * is a set value as far as wp_parse_args is concerned, so passing it
		 * through would render an empty block rather than falling back to the
		 * default field list.
		 */
		if ( ! empty( $fields ) ) {
			$args['fields'] = $fields;
		}

		$html = ChurchDetails::render( $args );

		if ( '' === $html && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$html = '<p class="sc-church__placeholder">'
				. esc_html__( 'Add your church details under Settings, Seedcast, Church.', 'seedcast-visitor-card' )
				. '</p>';
		}

		/*
		 * ChurchDetails::render() escapes every value it emits, so this is
		 * escaping the same thing twice. That is deliberate: it costs nothing
		 * here, and it means a future change to render() cannot turn this line
		 * into an XSS hole without anyone noticing. ChurchDetails emits no form
		 * controls, so wp_kses_post() has nothing of its to strip.
		 */
		echo wp_kses_post( $html );
	}
}

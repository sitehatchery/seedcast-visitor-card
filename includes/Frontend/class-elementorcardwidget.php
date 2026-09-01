<?php
/**
 * The Elementor widget itself.
 *
 * Loaded on demand by ElementorCard::register(), never on a site without
 * Elementor, because it extends a class Elementor owns.
 *
 * @package SeedcastVisitorCard\Frontend
 */

namespace SeedcastVisitorCard\Frontend;

use SeedcastVisitorCard\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * The visitor card as an Elementor widget.
 */
class ElementorCardWidget extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'seedcast-visitor-card';
	}

	/**
	 * Panel label.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Visitor Card', 'seedcast-visitor-card' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	/**
	 * Panel category.
	 *
	 * @return array
	 */
	public function get_categories() {
		return [ 'general' ];
	}

	/**
	 * Search terms.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return [ 'visitor', 'church', 'card', 'seedcast', 'visit' ];
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'scvc_content',
			[
				'label' => __( 'Visitor Card', 'seedcast-visitor-card' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'scvc_intro_note',
			[
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'What the card asks for is set once under Visitor Card, Settings. Anything left blank here uses that.', 'seedcast-visitor-card' ),
				'content_classes' => 'elementor-descriptor',
			]
		);

		$this->add_control(
			'template',
			[
				'label'   => __( 'Style', 'seedcast-visitor-card' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => Settings::get( 'scvc_template' ),
				'options' => Card::templates(),
			]
		);

		$this->add_control(
			'heading',
			[
				'label'       => __( 'Heading', 'seedcast-visitor-card' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => Settings::heading(),
			]
		);

		$this->add_control(
			'intro',
			[
				'label'       => __( 'Opening line', 'seedcast-visitor-card' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => Settings::intro(),
			]
		);

		$this->add_control(
			'button',
			[
				'label'       => __( 'Button', 'seedcast-visitor-card' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => Settings::button(),
			]
		);

		$this->add_control(
			'details',
			[
				'label'        => __( 'When and where', 'seedcast-visitor-card' ),
				'type'         => \Elementor\Controls_Manager::SELECT,
				'default'      => '',
				'options'      => array_merge(
					[ '' => __( 'Same as everywhere else', 'seedcast-visitor-card' ) ],
					Settings::details_modes()
				),
				'description'  => __( 'Your service day, times and address, taken from your church details.', 'seedcast-visitor-card' ),
			]
		);

		$this->end_controls_section();

		$this->register_style_controls();
	}

	/**
	 * Styling, on the Style tab where Elementor users expect it.
	 *
	 * Everything here writes to the same custom properties the stylesheet
	 * already reads, rather than adding a second set of rules that would
	 * compete with the card's own. That means a card styled here behaves the
	 * same as one styled in the Card Designer, and the two cannot disagree.
	 *
	 * @return void
	 */
	protected function register_style_controls() {
		$this->start_controls_section(
			'scvc_style',
			[
				'label' => __( 'Card', 'seedcast-visitor-card' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'accent',
			[
				'label'     => __( 'Accent color', 'seedcast-visitor-card' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .scvc-card' => '--scvc-accent: {{VALUE}};',
				],
				'description' => __( 'Leave empty to use the color set in the Card Designer.', 'seedcast-visitor-card' ),
			]
		);

		$this->add_control(
			'accent_text',
			[
				'label'     => __( 'Text on the accent', 'seedcast-visitor-card' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .scvc-card' => '--scvc-on-accent: {{VALUE}};',
				],
				'description' => __( 'Only set this if you have changed the accent. It is worked out for you otherwise.', 'seedcast-visitor-card' ),
				'condition'   => [ 'accent!' => '' ],
			]
		);

		$this->add_control(
			'header_color',
			[
				'label'     => __( 'Header color', 'seedcast-visitor-card' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .scvc-card' => '--scvc-accent-dark: {{VALUE}};',
				],
				'description' => __( 'The band across the top of the bold template.', 'seedcast-visitor-card' ),
				'condition'   => [ 'template' => 'bold' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'heading_typography',
				'label'    => __( 'Heading', 'seedcast-visitor-card' ),
				'selector' => '{{WRAPPER}} .scvc-card__heading',
			]
		);

		$this->add_control(
			'details_bg',
			[
				'label'     => __( 'When and where background', 'seedcast-visitor-card' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .scvc-card__details' => 'background: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'label_typography',
				'label'    => __( 'Field labels', 'seedcast-visitor-card' ),
				'selector' => '{{WRAPPER}} .scvc-card .sc-form__label',
			]
		);

		$this->add_responsive_control(
			'card_padding',
			[
				'label'      => __( 'Padding', 'seedcast-visitor-card' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .scvc-card, {{WRAPPER}} .scvc-card__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'card_radius',
			[
				'label'      => __( 'Corner radius', 'seedcast-visitor-card' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'selectors'  => [
					'{{WRAPPER}} .scvc-card' => 'border-radius: {{SIZE}}{{UNIT}}; overflow: hidden;',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'scvc_style_button',
			[
				'label' => __( 'Button', 'seedcast-visitor-card' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .scvc-card .sc-btn',
			]
		);

		$this->add_control(
			'button_bg',
			[
				'label'     => __( 'Background', 'seedcast-visitor-card' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .scvc-card .sc-btn' => 'background: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_text',
			[
				'label'     => __( 'Text', 'seedcast-visitor-card' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .scvc-card .sc-btn' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'button_padding',
			[
				'label'      => __( 'Padding', 'seedcast-visitor-card' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors'  => [
					'{{WRAPPER}} .scvc-card .sc-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'button_full',
			[
				'label'        => __( 'Full width', 'seedcast-visitor-card' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'selectors'    => [
					'{{WRAPPER}} .scvc-card .sc-btn' => 'width: 100%; text-align: center;',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Output.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$html = Card::render( [
			'template' => isset( $settings['template'] ) ? sanitize_key( $settings['template'] ) : '',
			'heading'  => isset( $settings['heading'] ) ? sanitize_text_field( $settings['heading'] ) : '',
			'intro'    => isset( $settings['intro'] ) ? sanitize_text_field( $settings['intro'] ) : '',
			'button'   => isset( $settings['button'] ) ? sanitize_text_field( $settings['button'] ) : '',
			'details'  => isset( $settings['details'] ) && '' !== $settings['details']
				? sanitize_key( $settings['details'] )
				: null,
		] );

		// Card::render() escapes everything it emits.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
	}
}

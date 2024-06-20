<?php

/**
 * Copyright © Ebrahim Byagowi, 2024
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

namespace MediaWiki\Extension\FontHandler;

use ImageHandler;
use MediaWiki\MediaWikiServices;
use ThumbnailImage;

class FontHandler extends ImageHandler {

	private const FONT_DEFAULT_RENDER_LANG = 'en';

	public function canRender( $file ) {
		return true;
	}

	public function validateParam( $name, $value ) {
		return in_array(
			$name, [ 'lang', 'text', 'dir', 'variations', 'features' ]
			) && $value > 0;
	}

	public function makeParamString( $params ) {
		return bin2hex( $params[ 'text' ] ?? wfMessage( 'fonthandler-sampletext' ) );
	}

	public function parseParamString( $str ) {
		return [ 'text' => pack( 'H*', $str ) ];
	}

	public function getParamMap() {
		return [
			// Text this font will be rendered with
			'fonthandler_text' => 'text',
			// Like SVG, exact same text can be drawn different for differently languages of the same script
			'img_lang' => 'lang',
			// Possible values: ltr/rtl/ttb/btt, default is auto detect
			'fonthandler_dir' => 'dir',
			// Similar to font-feature-settings of CSS, fonts can have parameter to tweak the rendering
			'fonthandler_features' => 'features',
			// Similar to font-variation-settings of CSS, variable fonts have a mechanism to tweak variable axes
			// For example a font with variable weight can be turned into Regular, Bold, Thin upon user request
			'fonthandler_variations' => 'variations',
		];
	}

	public function getImageSize( $image, $path ): array {
		return [ 640, 240, 'Svg' ];
	}

	public function getDimensionsString( $file ) {
		return "Font";
	}

	public function getThumbType( $ext, $mime, $params = null ) {
		return [ 'svg', 'image/svg+xml' ];
	}

	public function mustRender( $file ) {
		return true;
	}

	public function isVectorized( $file ) {
		return true;
	}

	public function doTransform( $image, $dstPath, $dstUrl, $params, $flags = 0 ) {
		$text = $params[ 'text' ] ?? wfMessage( 'fonthandler-sampletext' );
		$codes = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
		$codes_formatted = array_map(
			function ($code) {
				return sprintf("U+%04X", mb_ord( $code ) );
			},
			$codes
		);
		$result = MediaWikiServices::getInstance()->getShellCommandFactory()
			->create()
			->params(
				// Using pango's python binding is suggested for the actual implementation
				'/usr/bin/hb-view', # apt install libharfbuzz-bin
				'--background=#00000000',
				'--foreground=#000000',
				'--font-size=20',
				// It should be a BCP-47 code
				'--language=' . ( $params[ 'lang' ] ?? self::FONT_DEFAULT_RENDER_LANG ),
				// '--dir=' . ( $params[ 'dir' ] ?? 'auto' ) , // ltr/rtl/ttb/btt
				// '--variation=' . ( $params[ 'variations' ] ?? '' ), // e.g. wght=500
				// '--features=' . ( $params[ 'features' ] ?? '' ), // e.g. kern
				$image->getLocalRefPath(),
				'--unicodes=' . implode( ',', $codes_formatted ),
				'-o', $dstPath
			)
			->execute();
		echo $result->getStdout();
		echo $result->getStderr();

		return new ThumbnailImage( $image, $dstUrl, $dstPath, [
			'width' => 640,
			'height' => 240,
		] );
	}

	public function getSizeAndMetadata( $state, $path ) {
		return [];
	}

	public function formatMetadata( $image, $context = false ) {
		$result = MediaWikiServices::getInstance()->getShellCommandFactory()
			->create()
			->params(
				"/usr/bin/fc-query", # apt install fontconfig
				$image->getLocalRefPath(),
				'--format=%{fullname}:%{width}:%{weight}'
			)
			->execute()
			->getStdout();
		$parts = explode( ':', $result );
		$metadata = [
			'fonthandler-fullname' => $parts[0],
			'fonthandler-width' => $parts[1],
			'fonthandler-weight' => $parts[2],
		];

		return $this->formatMetadataHelper( $metadata, $context );
	}

	protected function visibleMetadataFields() {
		return [ 'fonthandler-fullname', 'fonthandler-width', 'fonthandler-weight' ];
	}

	public function isFileMetadataValid( $file ) {
		return self::METADATA_GOOD;
	}
}

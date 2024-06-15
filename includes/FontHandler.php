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

	public function canRender( $file ) {
		return true;
	}

	public function getImageSize( $image, $path ): array {
		return [ 640, 480, 'Svg' ];
	}

	public function getDimensionsString( $file ) {
		return "Scalable";
	}

	public function getThumbType( $ext, $mime, $params = null ) {
		return [ 'svg', 'image/svg+xml' ];
	}

	public function mustRender( $file ) {
		return true;
	}

	public function doTransform( $image, $dstPath, $dstUrl, $params, $flags = 0 ) {
		MediaWikiServices::getInstance()->getShellCommandFactory()
			->create()
			->params(
				'/usr/bin/hb-view', # apt install libharfbuzz-bin
				'--background=#00000000',
				'--foreground=#000000',
				$image->getLocalRefPath(),
				'The quick brown fox jumps over the lazy dog',
				'-o',
				$dstPath
			)
			->execute();

		return new ThumbnailImage( $image, $dstUrl, $dstPath, [
			'width' => 640,
			'height' => 480,
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
			'Full name' => $parts[0],
			'Width' => $parts[1],
			'Weight' => $parts[2],
		];

		return $this->formatMetadataHelper( $metadata, $context );
	}

	public function isFileMetadataValid( $file ) {
		return self::METADATA_GOOD;
	}
}

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [1.5.2] - 2024-04-15


### Fixed

- [#933](https://github.com/owncloud/files_classifier/pull/933) - fix: meta data detection after #928
- [#928](https://github.com/owncloud/files_classifier/issues/928) - fix: handling of docs without properties
- Dependencies updated


## [1.5.1] - 2024-02-23

### Fixed

- [#890](https://github.com/owncloud/files_classifier/issues/890) - UI elements for Document Classification missing when owncloud is installed under a webroute


## [1.5.0] - 2024-02-01

## Added

- [#796](https://github.com/owncloud/files_classifier/issues/796) - feat: PDF classification
  - feat: support images with exif headers
  - feat: add unit test for jpeg exif data extraction
- ATTENTION: this update requires the installation of exiftool.
  - E.g. for Debian & Ubuntu systems: apt install exiftool

## [1.4.0] - 2023-07-27

### Changed

- [#755](https://github.com/owncloud/files_classifier/issues/755) - [full-ci] Bump to Symfony 5
- Minimum core version 10.11, minimum php version 7.4
- Dependencies updated

## [1.3.2] - 2022-10-06

### Fixed
- [#656](https://github.com/owncloud/files_classifier/issues/656) - make sure to pass correct public link share node 
- [#660](https://github.com/owncloud/files_classifier/issues/660) - make sure to classify documents post move and copy


## [1.3.1] - 2021-05-03

### Changed

- Skip file classification if no document XML properties found - [#411](https://github.com/owncloud/files_classifier/issues/411)
- Handle expiration update for remote shares - [#370](https://github.com/owncloud/files_classifier/pull/370)

## [1.3.0] - 2020-06-08

### Added

- Move to the new licensing management - [#338](https://github.com/owncloud/files_classifier/issues/338)
- Support PHP 7.4 - [#328](https://github.com/owncloud/files_classifier/issues/328)

### Changed

- Set owncloud min-version to 10.5
- Bump libraries
- FIX SPDX License as per https://docs.npmjs.com/files/package.json#license - [#299](https://github.com/owncloud/files_classifier/issues/299)
- switch dependency from zendframework to laminas - [#300](https://github.com/owncloud/files_classifier/issues/300)

## [1.2.0] - 2020-02-26

### Changed

- [Security] Bump eslint-utils from 1.3.1 to 1.4.3 - [#273](https://github.com/owncloud/files_classifier/issues/273)
- Bump Symfony to 4.4, drop support for PHP 7.0 - [#293](https://github.com/owncloud/files_classifier/issues/293)
- Bump other libraries

## [1.1.0] - 2019-10-06

### Changed

- Bump Libraries
- Drop PHP 7.0 from drone CI - [#254](https://github.com/owncloud/files_classifier/issues/254)

### Fixed

- Fix tagging and deletion for public upload - [#249](https://github.com/owncloud/files_classifier/issues/249)

## [1.0.4] - 2019-10-17

### Added

- PHP 7.3 Support [#195](https://github.com/owncloud/files_classifier/pull/195)

### Changed

- Updated multiple dependencies

## [1.0.3] - 2019-03-20

### Added

- Add MS AIP as recommended classification manager to info.xml - [#130](https://github.com/owncloud/files_classifier/issues/130)
- Added static tags handling - [#139](https://github.com/owncloud/files_classifier/issues/139) [#141](https://github.com/owncloud/files_classifier/pull/141)

### Fixed

- Allow public link share expiration date up the lowest expiration value from the files inside the folder - [#134](https://github.com/owncloud/files_classifier/issues/134)

## [1.0.1] - 2018-12-07

### Changed

- Set max version to 10 because core is switching to Semver

## 1.0.0 - 2018-11-07

- Initial release

[Unreleased]: https://github.com/owncloud/files_classifier/compare/v1.5.2...master
[1.5.2]: https://github.com/owncloud/files_classifier/compare/v1.5.1...v1.5.2
[1.5.1]: https://github.com/owncloud/files_classifier/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/owncloud/files_classifier/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/owncloud/files_classifier/compare/v1.3.2...v1.4.0
[1.3.2]: https://github.com/owncloud/files_classifier/compare/v1.3.1...v1.3.2
[1.3.1]: https://github.com/owncloud/files_classifier/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/owncloud/files_classifier/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/owncloud/files_classifier/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/owncloud/files_classifier/compare/v1.0.4...v1.1.0
[1.0.4]: https://github.com/owncloud/files_classifier/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/owncloud/files_classifier/compare/v1.0.1...v1.0.3
[1.0.1]: https://github.com/owncloud/files_classifier/compare/v1.0.0...v1.0.1

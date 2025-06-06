# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [1.7.1] - 2025-03-11

### Changed

- [#337](https://github.com/owncloud/ransomware_protection/pull/337) - file fetcher shall only fetch from the filesystem and not via any php stream wrapper


## [1.7.0] - 2023-07-27

### Changed

- [#324](https://github.com/owncloud/ransomware_protection/pull/324) - Always return an int from Symfony Command execute method
- [#322](https://github.com/owncloud/ransomware_protection/pull/322) - Prevent default overwrite when setting file
- Minimum core version 10.11, minimum php version 7.4
- Dependencies updated

## [1.6.0] - 2023-01-19

### Added

- [#311](https://github.com/owncloud/ransomware_protection/pull/311) - Use an external file for the blacklist

## [1.5.0] - 2022-12-12

### Fixed

- [#221](https://github.com/owncloud/ransomware_protection/issues/221) - occ command ransomeware:restore could not restore files
- [#307](https://github.com/owncloud/ransomware_protection/issues/307) - occ command ransomware:restore restore files in main directory
- [#305](https://github.com/owncloud/ransomware_protection/issues/305) - Failed at phpstan in nightly build


## [1.4.0] - 2022-02-11

### Changed

- Update blacklist to 2022-02-10 https://fsrm.experiant.ca/ - [#279](https://github.com/owncloud/ransomware_protection/issues/279)
- License file added - [#278](https://github.com/owncloud/ransomware_protection/issues/278)


## [1.3.0] - 2020-06-18

### Added

- Move to the new licensing management - [#232](https://github.com/owncloud/ransomware_protection/issues/232)

### Changed

- Bump libraries

## [1.2.1] - 2020-03-05

### Changed

- Update blacklist to 2020-03-04 https://fsrm.experiant.ca/ - [#217](https://github.com/owncloud/ransomware_protection/issues/217)

## [1.2.0] - 2020-01-20

### Fixed

- Fix move/rename operations for unauthenticated users - [#202](https://github.com/owncloud/ransomware_protection/issues/202)

## [1.1.0] - 2018-12-03

### Added

- support for PHP 7.2 - [#130](https://github.com/owncloud/ransomware_protection/issues/130)

### Changed

- Set max version to 10 because core platform is switching to Semver
- Blacklist updated as at 29 Nov 2018 [#146](https://github.com/owncloud/ransomware_protection/pull/146)

## [1.0.3] - 2018-07-17

Compatibility with ownCloud up to 10.1

### Changed

- Blacklist updated as at 17. July 2018

## [1.0.2] - 2018-05-03

Compatibility with ownCloud 10.0.8

### Changed
- Blacklist updated as at 2. May 2018

## [1.0.1] - 2018-02-21

### Fixed

- Compatibility with ownCloud 10.0.7
- False positives related to case insensitive matching

### Changed

- Minor code cleanup
- Blacklist updated as at 19 Feb 2018

## 1.0 - 2017-12-19

- Initial release


[Unreleased]: https://github.com/owncloud/ransomware_protection/compare/v1.7.0...HEAD
[1.7.0]: https://github.com/owncloud/ransomware_protection/compare/v1.6.0...v1.7.0
[1.6.0]: https://github.com/owncloud/ransomware_protection/compare/v1.5.0...v1.6.0
[1.5.0]: https://github.com/owncloud/ransomware_protection/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/owncloud/ransomware_protection/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/owncloud/ransomware_protection/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/owncloud/ransomware_protection/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/owncloud/ransomware_protection/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/owncloud/ransomware_protection/compare/v1.0.3...v1.1.0
[1.0.3]: https://github.com/owncloud/ransomware_protection/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/owncloud/ransomware_protection/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/owncloud/ransomware_protection/compare/v1.0.0...v1.0.1

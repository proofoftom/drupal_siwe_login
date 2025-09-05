# Changelog

All notable changes to the SIWE Login module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- ENS validation feature to verify that ENS names in SIWE messages resolve to the signing Ethereum address
- New EnsResolver service for interacting with ENS contracts on Ethereum mainnet
- Configuration option for Ethereum provider URL
- Unit tests for ENS validation functionality

### Changed

- Enhanced SIWE message validation to include ENS name resolution when provided in resources
- Updated settings form to include Ethereum provider URL field
- Improved error handling and logging for ENS resolution failures

### Fixed

- None

### Deprecated

- None

### Removed

- None

### Security

- Enhanced authentication security by validating ENS names against Ethereum addresses

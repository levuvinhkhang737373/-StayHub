# Meilisearch Reliability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Chuẩn hóa và triển khai lại bốn index Meilisearch mà StayHub sử dụng.

**Architecture:** Giữ `config/scout.php` làm nguồn cấu hình index duy nhất. Thêm command triển khai idempotent để đồng bộ settings trước khi import dữ liệu, cùng test bảo vệ sự nhất quán giữa models, cấu hình và query.

**Tech Stack:** Laravel 13, Laravel Scout, Meilisearch, PHPUnit.

---

### Task 1: Regression tests cho cấu hình tìm kiếm

**Files:**
- Create: `tests/Feature/Search/MeilisearchConfigurationTest.php`

- [ ] Viết test xác nhận bốn model có index settings và mọi sortable field tồn tại trong searchable document.
- [ ] Viết test command deployment gọi sync settings trước import.
- [ ] Chạy test và xác nhận thất bại vì command chưa tồn tại.

### Task 2: Command triển khai index

**Files:**
- Create: `app/Console/Commands/SetupMeilisearch.php`

- [ ] Tạo command `search:setup` chạy `scout:sync-index-settings`.
- [ ] Import lần lượt Region, Building, Tenant và Invoice; dừng và trả lỗi nếu một bước thất bại.
- [ ] Chạy test và xác nhận đạt.

### Task 3: Đồng bộ môi trường và xác minh

**Files:**
- Modify only if tests expose a mismatch: `config/scout.php`

- [ ] Chạy toàn bộ test liên quan.
- [ ] Chạy `search:setup` trong container Laravel.
- [ ] Reload Octane.
- [ ] Truy vấn thử Meilisearch theo tên tòa nhà và khách thuê.

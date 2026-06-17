-- ============================================================
--  MIGRATION V3 — Bổ sung phương thức xét tuyển và hỗ trợ DGNL
--  Chạy file này MỘT LẦN trên database admission_system hiện tại
-- ============================================================

USE admission_system;

-- 1. Thêm phương thức xét tuyển cho từng bản ghi điểm
ALTER TABLE admission_scores
    ADD COLUMN method ENUM('THPT','HocBa','TongHop','DGNL','Thang')
    NOT NULL DEFAULT 'THPT' AFTER combination;

-- 2. Cho phép dữ liệu DGNL có thang điểm lớn hơn 30
--    và cho phép tổ hợp để trống với phương thức không cần tổ hợp
ALTER TABLE admission_scores
    MODIFY COLUMN combination VARCHAR(10) NULL,
    MODIFY COLUMN score DECIMAL(6,2) NOT NULL;

-- 3. Một ngành có thể có nhiều phương thức trong cùng năm và tổ hợp
ALTER TABLE admission_scores
    DROP INDEX uq_score,
    ADD UNIQUE KEY uq_score (
        university_id,
        major_id,
        year,
        combination,
        method
    );

-- 4. Tăng tốc các bộ lọc tra cứu phổ biến
ALTER TABLE admission_scores
    ADD INDEX idx_score_lookup (year, method, major_id, university_id),
    ADD INDEX idx_score_value (score);

-- 5. Bảng lịch sử AI cũng phải lưu được điểm DGNL
ALTER TABLE ai_logs
    MODIFY COLUMN user_score DECIMAL(6,2) NULL,
    ADD COLUMN major_id INT NULL AFTER province,
    ADD COLUMN method VARCHAR(20) NULL AFTER major_id,
    ADD INDEX idx_ai_created_at (created_at);

-- Kiểm tra kết quả sau khi chạy
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'admission_system'
  AND TABLE_NAME = 'admission_scores'
ORDER BY ORDINAL_POSITION;

-- Thêm hỗ trợ gửi ảnh trong chat
-- Chạy file này MỘT LẦN trên database wink_shoe_store hiện có.
USE wink_shoe_store;

ALTER TABLE chat_messages
    ADD COLUMN image VARCHAR(255) DEFAULT NULL COMMENT 'Tên file ảnh đính kèm (uploads/chat)' AFTER message;

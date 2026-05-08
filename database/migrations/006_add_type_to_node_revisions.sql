-- Add 'type' column to node_revisions for differentiating content vs mosaic revisions
ALTER TABLE node_revisions ADD COLUMN type VARCHAR(32) DEFAULT 'content' AFTER node_id;

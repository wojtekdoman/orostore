-- Fix all PostgreSQL sequences after restore
DO $$
DECLARE
    r RECORD;
    max_id BIGINT;
    seq_value BIGINT;
BEGIN
    -- Loop through all sequences
    FOR r IN 
        SELECT 
            sequence_name,
            regexp_replace(sequence_name, '_id_seq$', '') as table_name
        FROM information_schema.sequences 
        WHERE sequence_schema = 'public' 
        AND sequence_name LIKE '%_id_seq'
    LOOP
        -- Check if table exists
        IF EXISTS (
            SELECT 1 FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name = r.table_name
        ) THEN
            -- Check if id column exists
            IF EXISTS (
                SELECT 1 FROM information_schema.columns 
                WHERE table_schema = 'public' 
                AND table_name = r.table_name 
                AND column_name = 'id'
            ) THEN
                -- Get max ID from table
                EXECUTE format('SELECT COALESCE(MAX(id), 0) FROM %I', r.table_name) INTO max_id;
                
                -- Get current sequence value
                EXECUTE format('SELECT last_value FROM %I', r.sequence_name) INTO seq_value;
                
                -- If sequence is behind, update it
                IF max_id > seq_value THEN
                    EXECUTE format('SELECT setval(''%I'', %s)', r.sequence_name, max_id);
                    RAISE NOTICE 'Fixed sequence % from % to %', r.sequence_name, seq_value, max_id;
                END IF;
            END IF;
        END IF;
    END LOOP;
END $$;
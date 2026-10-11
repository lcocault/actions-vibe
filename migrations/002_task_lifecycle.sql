-- Cycle de vie des tâches : statut et date de clôture.

ALTER TABLE task
    ADD COLUMN status       VARCHAR(10) NOT NULL DEFAULT 'a_faire'
        CHECK (status IN ('a_faire', 'en_cours', 'terminee')),
    ADD COLUMN completed_at TIMESTAMPTZ,
    ADD CONSTRAINT task_completed_at_check
        CHECK ((status = 'terminee') = (completed_at IS NOT NULL)),
    -- Une tâche périodique doit avoir une date butée de référence.
    ADD CONSTRAINT task_recurrence_due_date_check
        CHECK (recurrence IS NULL OR due_date IS NOT NULL);

CREATE INDEX task_status_idx ON task (status);

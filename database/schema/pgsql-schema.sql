--
-- PostgreSQL database dump
--

\restrict YUAEpz3POujeex9WS7Qq9UpVhg3QG3wXAQelL2Or80GRL1txOS4vBSW6ylCYBS1

-- Dumped from database version 16.14
-- Dumped by pg_dump version 18.4 (Ubuntu 18.4-1.pgdg24.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activity_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_logs (
    id bigint NOT NULL,
    farm_id bigint NOT NULL,
    user_id bigint,
    action character varying(255) NOT NULL,
    entity_type character varying(255) NOT NULL,
    entity_id bigint,
    description text,
    created_at timestamp(0) without time zone,
    staff_id bigint
);


--
-- Name: activity_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.activity_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activity_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activity_logs_id_seq OWNED BY public.activity_logs.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: chat_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chat_messages (
    id bigint NOT NULL,
    chat_session_id bigint NOT NULL,
    role character varying(10) NOT NULL,
    content text NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: chat_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.chat_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: chat_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.chat_messages_id_seq OWNED BY public.chat_messages.id;


--
-- Name: chat_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chat_sessions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    title character varying(60),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: chat_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.chat_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: chat_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.chat_sessions_id_seq OWNED BY public.chat_sessions.id;


--
-- Name: daily_monitorings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.daily_monitorings (
    id bigint NOT NULL,
    user_id bigint,
    tank_id bigint NOT NULL,
    log_date date NOT NULL,
    ppm numeric(10,2) NOT NULL,
    ph numeric(5,2) NOT NULL,
    water_temperature numeric(5,2),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    staff_id bigint
);


--
-- Name: daily_monitorings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.daily_monitorings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: daily_monitorings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.daily_monitorings_id_seq OWNED BY public.daily_monitorings.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: farm_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.farm_users (
    id bigint NOT NULL,
    farm_id bigint NOT NULL,
    user_id bigint NOT NULL,
    role character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: farm_users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.farm_users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: farm_users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.farm_users_id_seq OWNED BY public.farm_users.id;


--
-- Name: farms; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.farms (
    id bigint NOT NULL,
    created_by bigint NOT NULL,
    name character varying(255) NOT NULL,
    address character varying(255),
    description character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: farms_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.farms_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: farms_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.farms_id_seq OWNED BY public.farms.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: nutrient_additions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.nutrient_additions (
    id bigint NOT NULL,
    user_id bigint,
    tank_id bigint NOT NULL,
    log_date date NOT NULL,
    ppm_before numeric(10,2) NOT NULL,
    ppm_after numeric(10,2) NOT NULL,
    nutrient_a_ml numeric(10,2) NOT NULL,
    nutrient_b_ml numeric(10,2) NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    staff_id bigint
);


--
-- Name: nutrient_additions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.nutrient_additions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nutrient_additions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.nutrient_additions_id_seq OWNED BY public.nutrient_additions.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: ph_down_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ph_down_logs (
    id bigint NOT NULL,
    user_id bigint,
    tank_id bigint NOT NULL,
    log_date date NOT NULL,
    ph_before numeric(5,2) NOT NULL,
    ph_after numeric(5,2) NOT NULL,
    ph_down_ml numeric(10,2) NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    staff_id bigint
);


--
-- Name: ph_down_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ph_down_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ph_down_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ph_down_logs_id_seq OWNED BY public.ph_down_logs.id;


--
-- Name: push_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.push_subscriptions (
    id bigint NOT NULL,
    fcm_token character varying(255) NOT NULL,
    platform character varying(255) DEFAULT 'android'::character varying NOT NULL,
    device_info character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    subscribable_type character varying(255),
    subscribable_id bigint
);


--
-- Name: push_subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.push_subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: push_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.push_subscriptions_id_seq OWNED BY public.push_subscriptions.id;


--
-- Name: reminder_occurrences; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reminder_occurrences (
    id bigint NOT NULL,
    reminder_id bigint NOT NULL,
    scheduled_at timestamp(0) without time zone NOT NULL,
    advance_notify_at timestamp(0) without time zone,
    advance_notified_at timestamp(0) without time zone,
    notified_at timestamp(0) without time zone,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    completed_by_type character varying(255),
    completed_by_id bigint,
    completed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: reminder_occurrences_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reminder_occurrences_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reminder_occurrences_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reminder_occurrences_id_seq OWNED BY public.reminder_occurrences.id;


--
-- Name: reminder_targets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reminder_targets (
    id bigint NOT NULL,
    reminder_id bigint NOT NULL,
    targetable_type character varying(255) NOT NULL,
    targetable_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: reminder_targets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reminder_targets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reminder_targets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reminder_targets_id_seq OWNED BY public.reminder_targets.id;


--
-- Name: reminders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reminders (
    id bigint NOT NULL,
    farm_id bigint NOT NULL,
    created_by_type character varying(255) NOT NULL,
    created_by_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    body text NOT NULL,
    starts_at timestamp(0) without time zone NOT NULL,
    recurrence json,
    advance_notify_minutes integer,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: reminders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reminders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reminders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reminders_id_seq OWNED BY public.reminders.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: staff; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.staff (
    id bigint NOT NULL,
    farm_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    username character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: staff_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.staff_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: staff_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.staff_id_seq OWNED BY public.staff.id;


--
-- Name: tanks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tanks (
    id bigint NOT NULL,
    farm_id bigint NOT NULL,
    created_by bigint NOT NULL,
    name character varying(255) NOT NULL,
    capacity_liter numeric(10,2) NOT NULL,
    notes text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    target_ppm_min numeric(10,2) DEFAULT '800'::numeric NOT NULL,
    target_ppm_max numeric(10,2) DEFAULT '1200'::numeric NOT NULL,
    target_ph_min numeric(4,2) DEFAULT 5.5 NOT NULL,
    target_ph_max numeric(4,2) DEFAULT 6.5 NOT NULL,
    current_ppm numeric(8,2),
    current_ph numeric(4,2),
    current_water_temperature numeric(4,2),
    last_condition_updated_at timestamp(0) without time zone
);


--
-- Name: tanks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tanks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tanks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tanks_id_seq OWNED BY public.tanks.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    is_admin boolean DEFAULT false NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: activity_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs ALTER COLUMN id SET DEFAULT nextval('public.activity_logs_id_seq'::regclass);


--
-- Name: chat_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_messages ALTER COLUMN id SET DEFAULT nextval('public.chat_messages_id_seq'::regclass);


--
-- Name: chat_sessions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_sessions ALTER COLUMN id SET DEFAULT nextval('public.chat_sessions_id_seq'::regclass);


--
-- Name: daily_monitorings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_monitorings ALTER COLUMN id SET DEFAULT nextval('public.daily_monitorings_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: farm_users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_users ALTER COLUMN id SET DEFAULT nextval('public.farm_users_id_seq'::regclass);


--
-- Name: farms id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farms ALTER COLUMN id SET DEFAULT nextval('public.farms_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: nutrient_additions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nutrient_additions ALTER COLUMN id SET DEFAULT nextval('public.nutrient_additions_id_seq'::regclass);


--
-- Name: ph_down_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ph_down_logs ALTER COLUMN id SET DEFAULT nextval('public.ph_down_logs_id_seq'::regclass);


--
-- Name: push_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.push_subscriptions_id_seq'::regclass);


--
-- Name: reminder_occurrences id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reminder_occurrences ALTER COLUMN id SET DEFAULT nextval('public.reminder_occurrences_id_seq'::regclass);


--
-- Name: reminder_targets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reminder_targets ALTER COLUMN id SET DEFAULT nextval('public.reminder_targets_id_seq'::regclass);


--
-- Name: reminders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reminders ALTER COLUMN id SET DEFAULT nextval('public.reminders_id_seq'::regclass);


--
-- Name: staff id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff ALTER COLUMN id SET DEFAULT nextval('public.staff_id_seq'::regclass);


--
-- Name: tanks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tanks ALTER COLUMN id SET DEFAULT nextval('public.tanks_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: activity_logs activity_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: chat_messages chat_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_messages
    ADD CONSTRAINT chat_messages_pkey PRIMARY KEY (id);


--
-- Name: chat_sessions chat_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_sessions
    ADD CONSTRAINT chat_sessions_pkey PRIMARY KEY (id);


--
-- Name: daily_monitorings daily_monitorings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_monitorings
    ADD CONSTRAINT daily_monitorings_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: farm_users farm_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_users
    ADD CONSTRAINT farm_users_pkey PRIMARY KEY (id);


--
-- Name: farms farms_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farms
    ADD CONSTRAINT farms_name_unique UNIQUE (name);


--
-- Name: farms farms_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farms
    ADD CONSTRAINT farms_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: nutrient_additions nutrient_additions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nutrient_additions
    ADD CONSTRAINT nutrient_additions_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: ph_down_logs ph_down_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ph_down_logs
    ADD CONSTRAINT ph_down_logs_pkey PRIMARY KEY (id);


--
-- Name: push_subscriptions push_subscriptions_fcm_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_fcm_token_unique UNIQUE (fcm_token);


--
-- Name: push_subscriptions push_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: reminder_occurrences reminder_occurrences_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reminder_occurrences
    ADD CONSTRAINT reminder_occurrences_pkey PRIMARY KEY (id);


--
-- Name: reminder_occurrences reminder_occurrences_reminder_id_scheduled_at_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reminder_occurrences
    ADD CONSTRAINT reminder_occurrences_reminder_id_scheduled_at_unique UNIQUE (reminder_id, scheduled_at);


--
-- Name: reminder_targets reminder_targets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reminder_targets
    ADD CONSTRAINT reminder_targets_pkey PRIMARY KEY (id);


--
-- Name: reminders reminders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reminders
    ADD CONSTRAINT reminders_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: staff staff_farm_id_username_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff
    ADD CONSTRAINT staff_farm_id_username_unique UNIQUE (farm_id, username);


--
-- Name: staff staff_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff
    ADD CONSTRAINT staff_pkey PRIMARY KEY (id);


--
-- Name: tanks tanks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tanks
    ADD CONSTRAINT tanks_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: chat_messages_chat_session_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_messages_chat_session_id_index ON public.chat_messages USING btree (chat_session_id);


--
-- Name: chat_sessions_user_id_updated_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_sessions_user_id_updated_at_index ON public.chat_sessions USING btree (user_id, updated_at);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: farms_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX farms_created_by_index ON public.farms USING btree (created_by);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: push_subscriptions_subscribable_type_subscribable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_subscriptions_subscribable_type_subscribable_id_index ON public.push_subscriptions USING btree (subscribable_type, subscribable_id);


--
-- Name: reminder_occurrences_completed_by_type_completed_by_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reminder_occurrences_completed_by_type_completed_by_id_index ON public.reminder_occurrences USING btree (completed_by_type, completed_by_id);


--
-- Name: reminder_targets_targetable_type_targetable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reminder_targets_targetable_type_targetable_id_index ON public.reminder_targets USING btree (targetable_type, targetable_id);


--
-- Name: reminders_created_by_type_created_by_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reminders_created_by_type_created_by_id_index ON public.reminders USING btree (created_by_type, created_by_id);


--
-- Name: reminders_farm_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reminders_farm_id_index ON public.reminders USING btree (farm_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: activity_logs activity_logs_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE CASCADE;


--
-- Name: activity_logs activity_logs_staff_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: activity_logs activity_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: chat_messages chat_messages_chat_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_messages
    ADD CONSTRAINT chat_messages_chat_session_id_foreign FOREIGN KEY (chat_session_id) REFERENCES public.chat_sessions(id) ON DELETE CASCADE;


--
-- Name: chat_sessions chat_sessions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_sessions
    ADD CONSTRAINT chat_sessions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: daily_monitorings daily_monitorings_staff_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_monitorings
    ADD CONSTRAINT daily_monitorings_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: daily_monitorings daily_monitorings_tank_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_monitorings
    ADD CONSTRAINT daily_monitorings_tank_id_foreign FOREIGN KEY (tank_id) REFERENCES public.tanks(id) ON DELETE CASCADE;


--
-- Name: daily_monitorings daily_monitorings_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_monitorings
    ADD CONSTRAINT daily_monitorings_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: farm_users farm_users_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_users
    ADD CONSTRAINT farm_users_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE CASCADE;


--
-- Name: farm_users farm_users_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_users
    ADD CONSTRAINT farm_users_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: nutrient_additions nutrient_additions_staff_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nutrient_additions
    ADD CONSTRAINT nutrient_additions_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: nutrient_additions nutrient_additions_tank_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nutrient_additions
    ADD CONSTRAINT nutrient_additions_tank_id_foreign FOREIGN KEY (tank_id) REFERENCES public.tanks(id) ON DELETE CASCADE;


--
-- Name: nutrient_additions nutrient_additions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nutrient_additions
    ADD CONSTRAINT nutrient_additions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: ph_down_logs ph_down_logs_staff_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ph_down_logs
    ADD CONSTRAINT ph_down_logs_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: ph_down_logs ph_down_logs_tank_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ph_down_logs
    ADD CONSTRAINT ph_down_logs_tank_id_foreign FOREIGN KEY (tank_id) REFERENCES public.tanks(id) ON DELETE CASCADE;


--
-- Name: ph_down_logs ph_down_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ph_down_logs
    ADD CONSTRAINT ph_down_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: reminder_occurrences reminder_occurrences_reminder_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reminder_occurrences
    ADD CONSTRAINT reminder_occurrences_reminder_id_foreign FOREIGN KEY (reminder_id) REFERENCES public.reminders(id) ON DELETE CASCADE;


--
-- Name: reminder_targets reminder_targets_reminder_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reminder_targets
    ADD CONSTRAINT reminder_targets_reminder_id_foreign FOREIGN KEY (reminder_id) REFERENCES public.reminders(id) ON DELETE CASCADE;


--
-- Name: reminders reminders_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reminders
    ADD CONSTRAINT reminders_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE CASCADE;


--
-- Name: staff staff_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff
    ADD CONSTRAINT staff_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE CASCADE;


--
-- Name: tanks tanks_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tanks
    ADD CONSTRAINT tanks_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: tanks tanks_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tanks
    ADD CONSTRAINT tanks_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict YUAEpz3POujeex9WS7Qq9UpVhg3QG3wXAQelL2Or80GRL1txOS4vBSW6ylCYBS1

--
-- PostgreSQL database dump
--

\restrict 1WugfldOawa4LcSrBGmFXK474nh043VOZlN2slpnvse7XkgOTS6hAsbKlSGPxbU

-- Dumped from database version 16.14
-- Dumped by pg_dump version 18.4 (Ubuntu 18.4-1.pgdg24.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_06_27_000000_add_unique_index_to_users_name_column	1
5	2026_06_27_031021_create_farms_table	1
6	2026_06_27_075318_add_deleted_at_to_user_table	1
7	2026_07_01_000002_add_soft_deletes_to_farms_table	1
8	2026_07_01_000003_create_farm_users_table	1
9	2026_07_01_000004_create_tanks_table	1
10	2026_07_01_000005_create_daily_monitorings_table	1
11	2026_07_01_000006_create_nutrient_additions_table	1
12	2026_07_01_000007_create_ph_down_logs_table	1
13	2026_07_01_000008_create_activity_logs_table	1
14	2026_07_02_005444_add_target_fields_to_tanks_table	1
15	2026_07_02_013848_make_address_and_description_nullable_on_farms_table	1
16	2026_07_02_014557_make_water_temperature_nullable_on_daily_monitorings	1
17	2026_07_02_020000_add_current_state_to_tanks_table	1
18	2026_07_31_000001_create_chat_sessions_table	1
19	2026_07_31_000002_create_chat_messages_table	1
20	2026_08_01_000001_create_push_subscriptions_table	1
21	2026_08_02_000000_add_email_to_users_table	1
22	2026_08_02_000001_add_email_verified_at_to_users_table	1
23	2026_08_02_000001_rebuild_password_reset_tokens_table	1
24	2026_08_03_000000_drop_unique_index_on_users_name_column	2
25	2026_08_03_000000_create_staff_table	3
26	2026_08_03_000001_add_staff_id_to_transaction_tables	4
27	2026_08_03_000002_make_farms_name_unique	5
28	2026_08_03_000003_migrate_farm_member_role_to_manager	5
29	2026_08_03_100001_create_reminders_table	6
30	2026_08_03_100002_create_reminder_targets_table	6
31	2026_08_03_100003_create_reminder_occurrences_table	6
32	2026_08_03_100004_modify_push_subscriptions_table	6
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 32, true);


--
-- PostgreSQL database dump complete
--

\unrestrict 1WugfldOawa4LcSrBGmFXK474nh043VOZlN2slpnvse7XkgOTS6hAsbKlSGPxbU


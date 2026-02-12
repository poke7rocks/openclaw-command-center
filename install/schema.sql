-- OpenClaw Command Center Database Schema
-- Created: 2026-02-12
-- Database: openclaw_cc

-- Create database
CREATE DATABASE IF NOT EXISTS openclaw_cc;
USE openclaw_cc;

-- users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'viewer') DEFAULT 'viewer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- agents table
CREATE TABLE agents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    agent_name VARCHAR(100) NOT NULL,
    session_id VARCHAR(255) UNIQUE,
    status ENUM('active', 'idle', 'offline') DEFAULT 'offline',
    last_heartbeat TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON
);

-- heartbeats table
CREATE TABLE heartbeats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    agent_id INT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_time_ms INT,
    status VARCHAR(50),
    message TEXT,
    FOREIGN KEY (agent_id) REFERENCES agents(id)
);

-- metrics table
CREATE TABLE metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    metric_type VARCHAR(50) NOT NULL,
    metric_value DECIMAL(10,2),
    metric_unit VARCHAR(20),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    source VARCHAR(100),
    metadata JSON
);

-- gateway_logs table
CREATE TABLE gateway_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level ENUM('info', 'warning', 'error') DEFAULT 'info',
    message TEXT,
    context JSON,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- todoist_tasks table
CREATE TABLE todoist_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    todoist_id VARCHAR(50) UNIQUE,
    content TEXT,
    priority INT,
    due_date DATE NULL,
    completed BOOLEAN DEFAULT FALSE,
    sync_status ENUM('synced', 'pending', 'error') DEFAULT 'pending',
    last_sync TIMESTAMP NULL,
    metadata JSON
);

-- api_usage table
CREATE TABLE api_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service VARCHAR(50) NOT NULL,
    endpoint VARCHAR(255),
    tokens_used INT,
    cost_usd DECIMAL(10,6),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON
);

-- config_settings table
CREATE TABLE config_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

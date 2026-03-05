#
# Add recurring event fields to tx_news_domain_model_news
#
CREATE TABLE tx_news_domain_model_news (
	recurring_event tinyint(1) unsigned DEFAULT '0' NOT NULL,
	recurring_type varchar(20) DEFAULT '' NOT NULL ,
	recurring_interval int(11) unsigned DEFAULT '1' NOT NULL,
	recurring_days varchar(255) DEFAULT '1' NOT NULL,
	recurring_until int(11) unsigned DEFAULT '0' NOT NULL,
	recurring_count int(11) unsigned DEFAULT '0' NOT NULL,
	recurring_exclude_dates text DEFAULT '' NOT NULL,
	recurring_time_ranges text DEFAULT '' NOT NULL,
	recurring_exclude_school_holidays tinyint(1) unsigned DEFAULT '0' NOT NULL,
	recurring_exclude_public_holidays tinyint(1) unsigned DEFAULT '0' NOT NULL,
	recurring_monthly_week tinyint(4) DEFAULT '0' NOT NULL,
	recurring_monthly_weekday tinyint(4) DEFAULT '0' NOT NULL
);

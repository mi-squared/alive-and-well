-- table.sql
CREATE TABLE `aa_import_batch` (
`id` int(11) NOT NULL,
`status` varchar(255) NOT NULL DEFAULT 'waiting',
`filename` varchar(255) DEFAULT NULL,
`user_filename` varchar(255) NOT NULL,
`start_datetime` datetime NOT NULL,
`end_datetime` datetime NOT NULL,
`created_datetime` datetime NOT NULL,
`messages` text NOT NULL,
`record_count` int(11) NOT NULL COMMENT 'count of records on sheet',
`num_modified` bigint(20) NOT NULL,
`num_inserted` bigint(20) NOT NULL,
`error_count` int(11) NOT NULL COMMENT 'count of errors'
) ENGINE=InnoDB;
--
-- Indexes for table `aa_mss_batch`
--
ALTER TABLE `aa_import_batch`
    ADD PRIMARY KEY (`id`);

ALTER TABLE `aa_import_batch`
    MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

CREATE TABLE `aa_import_batch_delta` (
`id` bigint(20) NOT NULL,
`pid` bigint(20) NOT NULL,
`batch_id` bigint(20) NOT NULL,
`type` varchar(255) NOT NULL,
`field` varchar(255) NOT NULL,
`original_value` text,
`new_value` text
) ENGINE=InnoDB;

--
-- Indexes for table `aa_mss_batch_delta`
--
ALTER TABLE `aa_import_batch_delta`
    ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `aa_mss_batch_delta`
--
ALTER TABLE `aa_import_batch_delta`
    MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

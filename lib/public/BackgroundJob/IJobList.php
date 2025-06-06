<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 *
 * @copyright Copyright (c) 2022, LNKASIA TECHSOL
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCP\BackgroundJob;

/**
 * Interface IJobList
 *
 * @package OCP\BackgroundJob
 * @since 7.0.0
 */
interface IJobList {
	/**
	 * Add a job to the list
	 *
	 * @param \OCP\BackgroundJob\IJob|string $job
	 * @param mixed $argument The argument to be passed to $job->run() when the job is executed
	 * @since 7.0.0
	 */
	public function add($job, $argument = null);

	/**
	 * Remove a job from the list
	 *
	 * @param \OCP\BackgroundJob\IJob|string $job
	 * @param mixed $argument
	 * @since 7.0.0
	 */
	public function remove($job, $argument = null);

	/**
	 * check if a job is in the list
	 *
	 * @param \OCP\BackgroundJob\IJob|string $job
	 * @param mixed $argument
	 * @return bool
	 * @since 7.0.0
	 */
	public function has($job, $argument);

	/**
	 * get all jobs in the list
	 *
	 * @return \OCP\BackgroundJob\IJob[]
	 * @since 7.0.0
	 * @deprecated 9.0.0 - This method is dangerous since it can cause load and
	 * memory problems when creating too many instances.
	 */
	public function getAll();

	/**
	 * get the next job in the list, allocating reservation to the job
	 *
	 * @return \OCP\BackgroundJob\IJob|null
	 * @since 7.0.0
	 */
	public function getNext();

	/**
	 * @param int $id
	 * @return \OCP\BackgroundJob\IJob|null
	 * @since 7.0.0
	 */
	public function getById($id);

	/**
	 * @param int $id
	 * @return bool
	 * @since 10.13.0
	 */
	public function jobIdExists($id);

	/**
	 * set the job that was last ran to the current time
	 *
	 * @param \OCP\BackgroundJob\IJob $job
	 * @since 7.0.0
	 */
	public function setLastJob($job);

	/**
	 * Remove the reservation for a job
	 *
	 * @param IJob $job
	 * @since 9.1.0
	 */
	public function unlockJob($job);

	/**
	 * get the id of the last ran job
	 *
	 * @return int
	 * @since 7.0.0
	 * @deprecated 9.1.0 - The functionality behind the value is deprecated, it
	 *    only tells you which job finished last, but since we now allow multiple
	 *    executors to run in parallel, it's not used to calculate the next job.
	 */
	public function getLastJob();

	/**
	 * set the lastRun of $job to now
	 *
	 * @param \OCP\BackgroundJob\IJob $job
	 * @since 7.0.0
	 */
	public function setLastRun($job);

	/**
	 * set the lastRun of $job to now
	 *
	 * @param \OCP\BackgroundJob\IJob $job
	 * @param int $timeTaken
	 * @since 10.0.0
	 */
	public function setExecutionTime($job, $timeTaken);

	/**
	 * iterate over all valid jobs in the queue
	 *
	 * The callback will be called for each job.
	 * An IJob object will be passed to the callback.
	 * The callback should do whatever the caller wants with the IJob.
	 * For example, generate some output about the job.
	 *
	 * The callback should return a boolean.
	 * If true, then the iteration will continue to the next job.
	 * If false, then the iteration stops early.
	 *
	 * @param \Closure $callback callback(IJob $job):boolean
	 * @return void
	 * @since 10.2.0
	 */
	public function listJobs(\Closure $callback);

	/**
	 * iterate over all jobs in the queue, including invalid jobs
	 *
	 * The validJobCallback will be called for each job that has a valid class.
	 * An IJob object will be passed to the callback.
	 * The callback should do whatever the caller wants with the IJob.
	 * For example, generate some output about the job.
	 *
	 * The invalidJobCallback will be called for each job that does not have a valid class.
	 * In this case, it is not possible to construct an IJob object, so an array
	 * of data about the job will be passed to the callback.
	 * The array has keys for 'id', 'class', 'argument', 'last_run', 'last_checked',
	 * 'reserved_at' and 'execution_duration'.
	 * The callback should do whatever the caller wants with the data about the job.
	 * For example, generate some output about the job.
	 *
	 * Each callback should return a boolean.
	 * If true, then the iteration will continue to the next job.
	 * If false, then the iteration stops early.
	 *
	 * @param \Closure $validJobCallback callback(IJob $job):boolean
	 * @param \Closure $invalidJobCallback callback(array $row):boolean
	 * @return void
	 * @since 10.13.0
	 */
	public function listJobsIncludingInvalid(\Closure $validJobCallback, \Closure $invalidJobCallback): void;

	/**
	 * remove a specific job by id
	 * @return void
	 * @since 10.2.0
	 */
	public function removeById($id);
}

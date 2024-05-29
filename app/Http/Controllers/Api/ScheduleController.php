<?php

namespace App\Http\Controllers\Api;

use App\Helpers\HttpResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Buss;
use App\Models\Reservation;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    protected $responseFormatter;
    protected $user;

    public function __construct(HttpResponseFormatter $responseFormatter)
    {
        $this->responseFormatter = $responseFormatter;
        $this->user = auth('api')->user();
    }

    public function index(Request $request)
    {
        try {
            $schedules = Schedule::with(['bus', 'fromStation', 'toStation'])->get();

            return $this->responseFormatter->setStatusCode(200)
                ->setMessage('Success!')
                ->setResult(['schedules' => $schedules])
                ->format();
        } catch (\Exception $e) {
            return $this->responseFormatter->setStatusCode(500)
                ->setMessage('Error!')
                ->setResult(['erros' => $e])
                ->format();
        }
    }

    public function reserveTicket(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'schedule_id' => 'required|exists:schedules,id',
                'tickets_booked' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return $this->responseFormatter->setStatusCode(400)
                    ->setMessage('Validation Error!')
                    ->setResult(['error' => $validator->errors()])
                    ->format();
            }

            $user = auth('api')->user();

            if (!$user) {
                return $this->responseFormatter->setStatusCode(401)
                    ->setMessage('Unauthenticated')
                    ->setResult(['error' => 'Unauthenticated'])
                    ->format();
            }

            // Find the schedule by schedule_id
            $schedule = Schedule::find($request->input('schedule_id'));

            if (!$schedule) {
                return $this->responseFormatter->setStatusCode(404)
                    ->setMessage('Schedule Not Found')
                    ->setResult(['error' => 'Schedule not found'])
                    ->format();
            }

            // Find the bus associated with the schedule
            $bus = Buss::find($schedule->bus_id);

            if (!$bus) {
                return $this->responseFormatter->setStatusCode(404)
                    ->setMessage('Bus Not Found')
                    ->setResult(['error' => 'Bus not found'])
                    ->format();
            }

            // Check if there are enough chairs available
            if ($bus->chair < $request->tickets_booked) {
                return $this->responseFormatter->setStatusCode(400)
                    ->setMessage('Not Enough Chairs Available')
                    ->setResult(['error' => 'Not enough chairs available'])
                    ->format();
            }

            // Check if there is an existing reservation for the user and schedule
            $reservation = Reservation::where('user_id', $user->id)
                ->where('schedule_id', $schedule->id)
                ->where('status', 1)
                ->first();

            if ($reservation) {
                // If there is, add the booked tickets to the existing reservation
                $reservation->tickets_booked += $request->tickets_booked;
                $reservation->save();
            } else {
                // If not, create a new reservation
                $reservation = Reservation::create([
                    'user_id' => $user->id,
                    'bus_id' => $bus->id,
                    'schedule_id' => $schedule->id,
                    'tickets_booked' => $request->tickets_booked,
                    'date_departure' => $request->departure_date,
                    'status' => 1, // Status 1: reserved
                ]);
            }

            // Decrease the number of chairs available in the bus
            $bus->chair -= $request->tickets_booked;
            $bus->save();

            return $this->responseFormatter->setStatusCode(201)
                ->setMessage('Reservation Successful!')
                ->setResult(['reservation' => $reservation])
                ->format();
        } catch (\Exception $e) {
            return $this->responseFormatter->setStatusCode(500)
                ->setMessage('Error!')
                ->setResult(['error' => $e->getMessage()])
                ->format();
        }
    }

    public function updateReserveTicket(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'reservation_id' => 'required',
                'status' => 'required',
            ]);

            if ($validator->fails()) {
                return $this->responseFormatter->setStatusCode(400)
                    ->setMessage('Validation Error!')
                    ->setResult(['error' => $validator->errors()])
                    ->format();
            }

            $user = auth('api')->user();

            if (!$user) {
                return $this->responseFormatter->setStatusCode(401)
                    ->setMessage('Unauthenticated')
                    ->setResult(['error' => 'Unauthenticated'])
                    ->format();
            }

            // Find the reservation by ID
            $reservation = Reservation::findOrFail($request->reservation_id);

            // Update the status
            $reservation->status = $request->status;
            $reservation->save();

            return $this->responseFormatter->setStatusCode(201)
                ->setMessage('Update Reservation Successful!')
                ->format();
        } catch (\Exception $e) {
            return $this->responseFormatter->setStatusCode(500)
                ->setMessage('Error!')
                ->setResult(['error' => $e->getMessage()])
                ->format();
        }
    }


    public function historyReserve(Request $request)
    {
        try {
            $user = auth('api')->user();

            // Ensure the user is authenticated
            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            // Retrieve reservation for the authenticated user
            $schedules = Reservation::with(['schedule', 'schedule.fromStation', 'schedule.toStation', 'schedule.bus'])
                ->where('user_id', $user->id)
                ->get();


            return $this->responseFormatter->setStatusCode(200)
                ->setMessage('Success!')
                ->setResult(['reservations' => $schedules])
                ->format();
        } catch (\Exception $e) {
            return $this->responseFormatter->setStatusCode(500)
                ->setMessage('Error!')
                ->setResult(['errors' => $e->getMessage()])
                ->format();
        }
    }

    public function conductorReserveTicket(Request $request)
    {
        try {
            $user = auth('api')->user();

            // Ensure the user is authenticated
            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            $reservations = Reservation::with([
                'user',
                'bus', // Eager load the related bus data
                'bus.drivers',
                'bus.busConductors',
                'schedule',
                'schedule.fromStation',
                'schedule.toStation',
            ])
                ->whereHas('bus.busConductors', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                })
                ->get();


            return $this->responseFormatter->setStatusCode(200)
                ->setMessage('Success!')
                ->setResult(['costumer_reservations' => $reservations])
                ->format();
        } catch (\Exception $e) {
            return $this->responseFormatter->setStatusCode(500)
                ->setMessage('Error!')
                ->setResult(['errors' => $e->getMessage()])
                ->format();
        }
    }
    public function getDateDeparture($reservationId)
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('api')->user();

            if (!$user) {
                return response()->json([
                    'status_code' => 401,
                    'message' => 'Unauthenticated',
                    'result' => ['error' => 'Unauthenticated']
                ], 401);
            }

            // Find the reservation by reservation ID
            $reservation = Reservation::find($reservationId);

            if (!$reservation) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Reservation Not Found',
                    'result' => ['error' => 'Reservation not found']
                ], 404);
            }

            // Check if the reservation belongs to the authenticated user
            if ($reservation->user_id !== $user->id) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'Forbidden',
                    'result' => ['error' => 'You do not have permission to access this reservation']
                ], 403);
            }

            // Retrieve the date_departure from the reservation
            $dateDeparture = $reservation->date_departure;

            return response()->json([
                'status_code' => 200,
                'message' => 'Date Departure Retrieved Successfully',
                'result' => ['date_departure' => $dateDeparture]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Error!',
                'result' => ['error' => $e->getMessage()]
            ], 500);
        }
    }

}
